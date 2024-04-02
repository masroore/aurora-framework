<?php

namespace Aurora\Redis\Connectors;

use Aurora\Arr;
use Aurora\Redis\Connections\PhpRedisClusterConnection;
use Aurora\Redis\Connections\PhpRedisConnection;
use Redis;

class PhpRedisConnector
{
    /**
     * Create a new clustered Predis connection.
     *
     * @return PhpRedisConnection
     */
    public function connect(array $config, array $options)
    {
        return new PhpRedisConnection($this->createClient(array_merge(
            $config,
            $options,
            Arr::pull($config, 'options', [])
        )));
    }

    /**
     * Create a new clustered Predis connection.
     *
     * @return PhpRedisClusterConnection
     */
    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        $options = array_merge($options, $clusterOptions, Arr::pull($config, 'options', []));

        return new PhpRedisClusterConnection($this->createRedisClusterInstance(
            array_map([$this, 'buildClusterConnectionString'], $config),
            $options
        ));
    }

    /**
     * Build a single cluster seed string from array.
     *
     * @return string
     */
    protected function buildClusterConnectionString(array $server)
    {
        return $server['host'] . ':' . $server['port'] . '?' . http_build_query(Arr::only($server, [
            'database', 'password', 'prefix', 'read_timeout',
        ]));
    }

    /**
     * Create the Redis client instance.
     *
     * @return \Redis
     */
    protected function createClient(array $config)
    {
        return tap(new \Redis(), function ($client) use ($config): void {
            $this->establishConnection($client, $config);

            if (!empty($config['password'])) {
                $client->auth($config['password']);
            }

            if (!empty($config['database'])) {
                $client->select($config['database']);
            }

            if (!empty($config['prefix'])) {
                $client->setOption(\Redis::OPT_PREFIX, $config['prefix']);
            }

            if (!empty($config['read_timeout'])) {
                $client->setOption(\Redis::OPT_READ_TIMEOUT, $config['read_timeout']);
            }
        });
    }

    /**
     * Establish a connection with the Redis host.
     *
     * @param \Redis $client
     */
    protected function establishConnection($client, array $config): void
    {
        $client->{true === Arr::get($config, 'persistent', false) ? 'pconnect' : 'connect'}(
            $config['host'],
            $config['port'],
            Arr::get($config, 'timeout', 0)
        );
    }

    /**
     * Create a new redis cluster instance.
     *
     * @return \RedisCluster
     */
    protected function createRedisClusterInstance(array $servers, array $options)
    {
        return new \RedisCluster(
            null,
            array_values($servers),
            Arr::get($options, 'timeout', 0),
            Arr::get($options, 'read_timeout', 0),
            isset($options['persistent']) && $options['persistent']
        );
    }
}
