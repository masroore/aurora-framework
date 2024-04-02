<?php

namespace Aurora\Redis\Connections;

/**
 * @mixin \Predis\Client
 */
class PredisConnection extends Connection
{
    /**
     * Create a new Predis connection.
     *
     * @param \Predis\Client $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     * @param string       $method
     */
    public function createSubscription($channels, \Closure $callback, $method = 'subscribe'): void
    {
        $loop = $this->pubSubLoop();

        \call_user_func_array([$loop, $method], (array)$channels);

        foreach ($loop as $message) {
            if ('message' === $message->kind || 'pmessage' === $message->kind) {
                \call_user_func($callback, $message->payload, $message->channel);
            }
        }

        unset($loop);
    }
}
