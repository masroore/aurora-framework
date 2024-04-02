<?php

namespace Aurora;

class Memcached
{
    /**
     * The Memcached connection instance.
     *
     * @var Memcached
     */
    protected static $connection;

    /**
     * Dynamically pass all other method calls to the Memcache instance.
     *
     * <code>
     *        // Get an item from the Memcache instance
     *        $name = Memcached::get('name');
     *
     *        // Store data on the Memcache server
     *        Memcached::set('name', 'Taylor');
     * </code>
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::connection(), $method], $parameters);
    }

    /**
     * Get the Memcached connection instance.
     *
     * <code>
     *        // Get the Memcache connection and get an item from the cache
     *        $name = Memcached::connection()->get('name');
     *
     *        // Get the Memcache connection and place an item in the cache
     *        Memcached::connection()->set('name', 'Taylor');
     * </code>
     *
     * @return Memcached
     *
     * @throws \Exception
     */
    public static function connection()
    {
        if (null === static::$connection) {
            static::$connection = static::connect(Config::get('cache.memcached'));
        }

        return static::$connection;
    }

    /**
     * Create a new Memcached connection instance.
     *
     * @param array $servers
     *
     * @return Memcached
     *
     * @throws \Exception
     */
    protected static function connect($servers)
    {
        $memcache = new \Memcached();

        foreach ($servers as $server) {
            $memcache->addServer($server['host'], $server['port'], $server['weight']);
        }

        if (false === $memcache->getVersion()) {
            throw new \Exception('Could not establish memcached connection.');
        }

        return $memcache;
    }
}
