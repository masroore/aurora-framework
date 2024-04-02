<?php

namespace Aurora;

class Session
{
    public const SESSION_FOLDER = ''; // 'sessions'

    /**
     * The string name of the CSRF token stored in the session.
     *
     * @var string
     */
    public const CSRF_TOKEN = 'csrf_token';

    /**
     * The session singleton instance for the request.
     *
     * @var Session\Payload
     */
    public static $instance;

    /**
     * The third-party driver registrar.
     *
     * @var array
     */
    public static $registrar = [];

    /**
     * Magic Method for calling the methods on the session singleton instance.
     *
     * <code>
     *        // Retrieve a value from the session
     *        $value = Session::get('name');
     *
     *        // Write a value to the session storage
     *        $value = Session::put('name', 'Taylor');
     *
     *        // Equivalent statement using the "instance" method
     *        $value = Session::instance()->put('name', 'Taylor');
     * </code>
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::instance(), $method], $parameters);
    }

    /**
     * Create the session payload and load the session.
     */
    public static function boot(): void
    {
        static::start(Config::get('session.driver'));

        static::$instance->load(Cookie::get(Config::get('session.cookie')));
    }

    /**
     * Create the session payload instance for the request.
     *
     * @param string $driver
     */
    public static function start($driver): void
    {
        static::$instance = new Session\Payload(static::factory($driver));
    }

    /**
     * Create a new session driver instance.
     *
     * @param string $driver
     *
     * @return Session\Drivers\Driver
     */
    public static function factory($driver)
    {
        if (isset(static::$registrar[$driver])) {
            $resolver = static::$registrar[$driver];

            return $resolver();
        }

        switch ($driver) {
            case 'apc':
                return new Session\Drivers\APC(Cache::driver($driver));

            case 'apcu':
                return new Session\Drivers\APCu(Cache::driver($driver));

            case 'cookie':
                return new Session\Drivers\Cookie();

            case 'database':
                return new Session\Drivers\Database(Database::connection());

            case 'file':
                return new Session\Drivers\File(STORAGE_PATH . static::SESSION_FOLDER . DS);

            case 'memcached':
                return new Session\Drivers\Memcached(Cache::driver($driver));

            case 'memory':
                return new Session\Drivers\Memory();

            case 'redis':
                return new Session\Drivers\Redis(Cache::driver($driver));

            default:
                throw new \Exception("Session driver [$driver] is not supported.");
        }
    }

    /**
     * Register a third-party cache driver.
     *
     * @param string $driver
     */
    public static function extend($driver, \Closure $resolver): void
    {
        static::$registrar[$driver] = $resolver;
    }

    /**
     * Retrieve the active session payload instance for the request.
     *
     * <code>
     *        // Retrieve the session instance and get an item
     *        Session::instance()->get('name');
     *
     *        // Retrieve the session instance and place an item in the session
     *        Session::instance()->put('name', 'Taylor');
     * </code>
     *
     * @return Session\Payload
     */
    public static function instance()
    {
        if (static::started()) {
            return static::$instance;
        }

        throw new \Exception('A driver must be set before using the session.');
    }

    /**
     * Determine if session handling has been started for the request.
     *
     * @return bool
     */
    public static function started()
    {
        return null !== static::$instance;
    }

    /**
     * Get the default session driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return \Config::get('session.driver');
    }

    /**
     * Set the default session driver name.
     *
     * @param string $name
     */
    public static function setDefaultDriver($name): void
    {
        \Config::set('session.driver', $name);
    }
}
