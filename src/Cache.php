<?php

namespace Aurora;

class Cache
{
    public const FILE_CACHE_FOLDER = ''; // 'cache'

    /**
     * All of the active cache drivers.
     *
     * @var array
     */
    public static $drivers = [];

    /**
     * The third-party driver registrar.
     *
     * @var array
     */
    public static $registrar = [];

    /**
     * Magic Method for calling the methods on the default cache driver.
     *
     * <code>
     *        // Call the "get" method on the default cache driver
     *        $name = Cache::get('name');
     *
     *        // Call the "put" method on the default cache driver
     *        Cache::put('name', 'Taylor', 15);
     * </code>
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::driver(), $method], $parameters);
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
     * Get a cache driver instance.
     *
     * If no driver name is specified, the default will be returned.
     *
     * <code>
     *        // Get the default cache driver instance
     *        $driver = Cache::driver();
     *
     *        // Get a specific cache driver instance by name
     *        $driver = Cache::driver('memcached');
     * </code>
     *
     * @param string $driver
     *
     * @return Cache\Drivers\Driver
     */
    public static function driver($driver = null)
    {
        if (null === $driver) {
            $driver = Config::get('cache.driver');
        }

        if (!isset(static::$drivers[$driver])) {
            static::$drivers[$driver] = static::factory($driver);
        }

        return static::$drivers[$driver];
    }

    /**
     * Create a new cache driver instance.
     *
     * @param string $driver
     *
     * @return Cache\Drivers\Driver
     */
    protected static function factory($driver)
    {
        if (isset(static::$registrar[$driver])) {
            $resolver = static::$registrar[$driver];

            return $resolver();
        }

        switch ($driver) {
            case 'apc':
                return new Cache\Drivers\APC(Config::get('cache.key'));

            case 'apcu':
                return new Cache\Drivers\APCu(Config::get('cache.key'));

            case 'file':
                return new Cache\Drivers\File(STORAGE_PATH . static::FILE_CACHE_FOLDER . DS);

            case 'memcache':
                return new Cache\Drivers\Memcache(Memcache::connection(), Config::get('cache.key'));

            case 'memcached':
                return new Cache\Drivers\Memcached(Memcached::connection(), Config::get('cache.key'));

            case 'memory':
                return new Cache\Drivers\Memory();

            case 'redis':
                return new Cache\Drivers\Redis(Redis::connection());

            case 'database':
                return new Cache\Drivers\Database(Config::get('cache.key'));

            case 'wincache':
                return new Cache\Drivers\WinCache(Config::get('cache.key'));

            default:
                throw new \Exception("Cache driver {$driver} is not supported.");
        }
    }
}
