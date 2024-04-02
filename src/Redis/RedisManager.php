<?php

namespace Aurora\Redis;

use Aurora\Arr;
use Aurora\Redis\Connections\Connection;
use Aurora\Redis\Connectors\PhpRedisConnector;
use Aurora\Redis\Connectors\PredisConnector;

class RedisManager
{
    /**
     * The name of the default driver.
     *
     * @var string
     */
    protected $driver;

    /**
     * The Redis server configurations.
     *
     * @var array
     */
    protected $config;

    /**
     * The Redis connections.
     */
    protected $connections;

    /**
     * Create a new Redis manager instance.
     *
     * @param string $driver
     */
    public function __construct($driver, array $config)
    {
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * Pass methods onto the default Redis connection.
     *
     * @param string $method
     */
    public function __call($method, array $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }

    /**
     * Get a Redis connection by name.
     *
     * @param string|null $name
     *
     * @return Connection
     */
    public function connection($name = null)
    {
        $name = $name ?: 'default';

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given connection by name.
     *
     * @param string|null $name
     *
     * @return Connection
     */
    public function resolve($name = null)
    {
        $name = $name ?: 'default';

        $options = Arr::get($this->config, 'options', []);

        if (isset($this->config[$name])) {
            return $this->connector()->connect($this->config[$name], $options);
        }

        if (isset($this->config['clusters'][$name])) {
            return $this->resolveCluster($name);
        }

        throw new \InvalidArgumentException("Redis connection [{$name}] not configured.");
    }

    /**
     * Resolve the given cluster connection by name.
     *
     * @param string $name
     *
     * @return Connection
     */
    protected function resolveCluster($name)
    {
        $clusterOptions = Arr::get($this->config, 'clusters.options', []);

        return $this->connector()->connectToCluster(
            $this->config['clusters'][$name],
            $clusterOptions,
            Arr::get($this->config, 'options', [])
        );
    }

    /**
     * Get the connector instance for the current driver.
     *
     * @return PhpRedisConnector|PredisConnector
     */
    protected function connector()
    {
        switch ($this->driver) {
            case 'predis':
                return new PredisConnector();
            case 'phpredis':
                return new PhpRedisConnector();
        }
    }
}
