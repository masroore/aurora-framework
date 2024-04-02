<?php

namespace Aurora\Session;

use Aurora\Config;
use Aurora\Cookie;
use Aurora\Session;
use Aurora\Session\Drivers\Driver;
use Aurora\Session\Drivers\Sweeper;
use Aurora\Str;

class Payload
{
    /**
     * The session array that is stored by the driver.
     *
     * @var array
     */
    public $session;

    /**
     * The session driver used to retrieve and store the session payload.
     *
     * @var Driver
     */
    public $driver;

    /**
     * Indicates if the session already exists in storage.
     *
     * @var bool
     */
    public $exists = true;

    /**
     * Create a new session payload instance.
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Load the session for the current request.
     *
     * @param string $id
     */
    public function load($id): void
    {
        if (null !== $id) {
            $this->session = $this->driver->load($id);
        }

        // If the session doesn't exist or is invalid we will create a new session
        // array and mark the session as being non-existent. Some drivers, such as
        // the database driver, need to know whether it exists.
        if (null === $this->session || static::expired($this->session)) {
            $this->exists = false;

            $this->session = $this->driver->fresh();
        }

        // A CSRF token is stored in every session. The token is used by the Form
        // class and the "csrf" filter to protect the application from cross-site
        // request forgery attacks. The token is simply a random string.
        if (!$this->has(Session::CSRF_TOKEN)) {
            $this->put(Session::CSRF_TOKEN, Str::random(40));
        }
    }

    /**
     * Determine if the session or flash data contains an item.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return null !== $this->get($key);
    }

    /**
     * Get an item from the session.
     *
     * The session flash data will also be checked for the requested item.
     *
     * <code>
     *        // Get an item from the session
     *        $name = Session::get('name');
     *
     *        // Return a default value if the item doesn't exist
     *        $name = Session::get('name', 'Taylor');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public function get($key, $default = null)
    {
        $session = $this->session['data'];

        // We check for the item in the general session data first, and if it
        // does not exist in that data, we will attempt to find it in the new
        // and old flash data, or finally return the default value.
        if (null !== ($value = array_get($session, $key))) {
            return $value;
        }

        if (null !== ($value = array_get($session[':new:'], $key))) {
            return $value;
        }

        if (null !== ($value = array_get($session[':old:'], $key))) {
            return $value;
        }

        return value($default);
    }

    /**
     * Write an item to the session.
     *
     * <code>
     *        // Write an item to the session payload
     *        Session::put('name', 'Taylor');
     * </code>
     *
     * @param string $key
     */
    public function put($key, $value): void
    {
        array_set($this->session['data'], $key, $value);
    }

    /**
     * Keep all of the session flash data from expiring after the request.
     */
    public function reflash(): void
    {
        $old = $this->session['data'][':old:'];

        $this->session['data'][':new:'] = array_merge($this->session['data'][':new:'], $old);
    }

    /**
     * Keep a session flash item from expiring at the end of the request.
     *
     * <code>
     *        // Keep the "name" item from expiring from the flash data
     *        Session::keep('name');
     *
     *        // Keep the "name" and "email" items from expiring from the flash data
     *        Session::keep(array('name', 'email'));
     * </code>
     *
     * @param array|string $keys
     */
    public function keep($keys): void
    {
        foreach ((array)$keys as $key) {
            $this->flash($key, $this->get($key));
        }
    }

    /**
     * Write an item to the session flash data.
     *
     * Flash data only exists for the current and next request to the application.
     *
     * <code>
     *        // Write an item to the session payload's flash data
     *        Session::flash('name', 'Taylor');
     * </code>
     *
     * @param string $key
     */
    public function flash($key, $value): void
    {
        array_set($this->session['data'][':new:'], $key, $value);
    }

    /**
     * Remove an item from the session data.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        array_forget($this->session['data'], $key);
    }

    /**
     * Remove all of the items from the session.
     *
     * The CSRF token will not be removed from the session.
     */
    public function flush(): void
    {
        $token = $this->token();

        $sess = [Session::CSRF_TOKEN => $token, ':new:' => [], ':old:' => []];

        $this->session['data'] = $sess;
    }

    /**
     * Get the CSRF token that is stored in the session data.
     *
     * @return string
     */
    public function token()
    {
        return $this->get(Session::CSRF_TOKEN);
    }

    /**
     * Generate a new session identifier.
     */
    public function regenerate(): void
    {
        $this->migrate(false);
    }

    /**
     * Generate a new session identifier, and optionally delete current session.
     *
     * @param bool $destroy
     */
    public function migrate($destroy = false): void
    {
        if ($destroy) {
            $this->driver->delete($this->session['id']);
        }
        $this->session['id'] = $this->driver->id();

        $this->exists = false;
    }

    /**
     * Get the last activity for the session.
     *
     * @return int
     */
    public function activity()
    {
        return $this->session['last_activity'];
    }

    /**
     * Store the session payload in storage.
     *
     * This method will be called automatically at the end of the request.
     */
    public function save(): void
    {
        $this->session['last_activity'] = time();

        // Session flash data is only available during the request in which it
        // was flashed and the following request. We will age the data so that
        // it expires at the end of the user's next request.
        $this->age();

        $config = Config::get('session');

        // The responsibility of actually storing the session information in
        // persistent storage is delegated to the driver instance being used
        // by the session payload.
        //
        // This allows us to keep the payload very generic, while moving the
        // platform or storage mechanism code into the specialized drivers,
        // keeping our code very dry and organized.
        $this->driver->save($this->session, $config, $this->exists);

        // Next we'll write out the session cookie. This cookie contains the
        // ID of the session, and will be used to determine the owner of the
        // session on the user's subsequent requests to the application.
        $this->cookie($config);

        // Some session drivers implement the Sweeper interface meaning that
        // they must clean up expired sessions manually. Here we'll calculate
        // if we need to run garbage collection.
        $sweepage = $config['sweepage'];

        if (mt_rand(1, $sweepage[1]) <= $sweepage[0]) {
            $this->sweep();
        }
    }

    /**
     * Clean up expired sessions.
     *
     * If the session driver is a sweeper, it must clean up expired sessions
     * from time to time. This method triggers garbage collection.
     */
    public function sweep(): void
    {
        if ($this->driver instanceof Sweeper) {
            $this->driver->sweep(time() - (Config::get('session.lifetime') * 60));
        }
    }

    /**
     * Determine if the session payload instance is valid.
     *
     * The session is considered valid if it exists and has not expired.
     *
     * @param array $session
     *
     * @return bool
     */
    protected static function expired($session)
    {
        $lifetime = Config::get('session.lifetime');

        return (time() - $session['last_activity']) > ($lifetime * 60);
    }

    /**
     * Age the session flash data.
     */
    protected function age(): void
    {
        $this->session['data'][':old:'] = $this->session['data'][':new:'];

        $this->session['data'][':new:'] = [];
    }

    /**
     * Send the session ID cookie to the browser.
     *
     * @param array $config
     */
    protected function cookie($config): void
    {
        extract($config, \EXTR_SKIP);

        $minutes = (!$expire_on_close) ? $lifetime : 0;

        Cookie::put($cookie, $this->session['id'], $minutes, $path, $domain, $secure);
    }
}
