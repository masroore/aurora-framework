<?php

namespace Aurora\Cache\Drivers;

abstract class Driver
{
    protected $prefix;

    protected $suffix;

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    abstract public function has($key);

    /**
     * Write an item to the cache for a given number of minutes.
     *
     * <code>
     *        // Put an item in the cache for 15 minutes
     *        Cache::put('name', 'Taylor', 15);
     * </code>
     *
     * @param string $key
     * @param int    $minutes
     */
    abstract public function put($key, $value, $minutes): void;

    /**
     * Get an item from the cache, or cache the default value forever.
     *
     * @param string $key
     */
    public function sear($key, $default)
    {
        return $this->remember($key, $default, null, 'forever');
    }

    /**
     * Get an item from the cache, or cache and return the default value.
     *
     * <code>
     *        // Get an item from the cache, or cache a value for 15 minutes
     *        $name = Cache::remember('name', 'Taylor', 15);
     *
     *        // Use a closure for deferred execution
     *        $count = Cache::remember('count', function() { return User::count(); }, 15);
     * </code>
     *
     * @param string $key
     * @param int    $minutes
     * @param string $function
     */
    public function remember($key, $default, $minutes, $function = 'put')
    {
        if (null !== ($item = $this->get($key, null))) {
            return $item;
        }

        $this->$function($key, $default = value($default), $minutes);

        return $default;
    }

    /**
     * Get an item from the cache.
     *
     * <code>
     *        // Get an item from the cache driver
     *        $name = Cache::driver('name');
     *
     *        // Return a default value if the requested item isn't cached
     *        $name = Cache::get('name', 'Taylor');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public function get($key, $default = null)
    {
        return (null !== ($item = $this->retrieve($key))) ? $item : value($default);
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    abstract public function forget($key): void;

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @param string $suffix
     *
     * @return Driver
     */
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * @param string $prefix
     *
     * @return Driver
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    abstract protected function retrieve($key);

    /**
     * Get the expiration time as a UNIX timestamp.
     *
     * @param int $minutes
     *
     * @return int
     */
    protected function expiration($minutes)
    {
        return time() + ($minutes * 60);
    }
}
