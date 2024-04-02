<?php

namespace Aurora;

use Aurora\Redis\RedisManager;

class Redis
{
    /**
     * The active Redis connection instance.
     *
     * @var RedisManager
     */
    protected static $connection;

    /**
     * Dynamically pass static method calls to the Redis instance.
     *
     * @param string $method
     */
    public static function __callStatic($method, array $parameters)
    {
        return static::connection()->run($method, $parameters);
    }

    /**
     * Dynamically make calls to the Redis database.
     */
    public function __call($method, $parameters)
    {
        return $this->run($method, $parameters);
    }

    /**
     * Get a Redis database connection instance.
     *
     * The given name should correspond to a Redis database in the configuration file.
     *
     * <code>
     *        // Get the default Redis database instance
     *        $redis = Redis::db();
     *
     *        // Get a specified Redis database instance
     *        $reids = Redis::db('redis_2');
     * </code>
     *
     * @return RedisManager
     */
    public static function connection()
    {
        if (!isset(static::$connection)) {
            $config = Config::get('database.redis');
            static::$connection = new RedisManager(Arr::pull($config, 'client', 'predis'), $config);
        }

        return static::$connection;
    }

    /**
     * Execute a command against the Redis database.
     *
     * <code>
     *        // Execute the GET command for the "name" key
     *        $name = Redis::db()->run('get', array('name'));
     *
     *        // Execute the LRANGE command for the "list" key
     *        $list = Redis::db()->run('lrange', array(0, 5));
     * </code>
     *
     * @param string $method
     * @param array  $parameters
     */
    public function run($method, $parameters)
    {
        return static::connection()->run($method, $parameters);
    }
}
