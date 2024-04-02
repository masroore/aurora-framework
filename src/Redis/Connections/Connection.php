<?php

namespace Aurora\Redis\Connections;

/**
 * @mixin \Predis\Client
 */
abstract class Connection
{
    /**
     * The Predis client.
     *
     * @var \Predis\Client
     */
    protected $client;

    /**
     * Pass other method calls down to the underlying client.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __call($method, $parameters)
    {
        return $this->command($method, $parameters);
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     * @param string       $method
     */
    abstract public function createSubscription($channels, \Closure $callback, $method = 'subscribe'): void;

    /**
     * Get the underlying Redis client.
     */
    public function client()
    {
        return $this->client;
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     */
    public function subscribe($channels, \Closure $callback): void
    {
        $this->createSubscription($channels, $callback, __FUNCTION__);
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     *
     * @param array|string $channels
     */
    public function psubscribe($channels, \Closure $callback): void
    {
        $this->createSubscription($channels, $callback, __FUNCTION__);
    }

    /**
     * Run a command against the Redis database.
     *
     * @param string $method
     */
    public function command($method, array $parameters = [])
    {
        return $this->client->{$method}(...$parameters);
    }
}
