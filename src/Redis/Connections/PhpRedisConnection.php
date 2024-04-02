<?php

namespace Aurora\Redis\Connections;

use Redis;

/**
 * @mixin \Redis
 */
class PhpRedisConnection extends Connection
{
    /**
     * Create a new PhpRedis connection.
     *
     * @param \Redis $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Pass other method calls down to the underlying client.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __call($method, $parameters)
    {
        $method = mb_strtolower($method);

        if ('eval' === $method) {
            return $this->proxyToEval($parameters);
        }

        if ('zrangebyscore' === $method || 'zrevrangebyscore' === $method) {
            $parameters = array_map(static fn ($parameter) => \is_array($parameter) ? array_change_key_case($parameter) : $parameter, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Returns the value of the given key.
     *
     * @param string $key
     *
     * @return string|null
     */
    public function get($key)
    {
        $result = $this->client->get($key);

        return false !== $result ? $result : null;
    }

    /**
     * Get the values of all the given keys.
     *
     * @return array
     */
    public function mget(array $keys)
    {
        return array_map(static fn ($value) => false !== $value ? $value : null, $this->client->mget($keys));
    }

    /**
     * Set the string value in argument as value of the key.
     *
     * @param string      $key
     * @param string|null $expireResolution
     * @param int|null    $expireTTL
     * @param string|null $flag
     *
     * @return bool
     */
    public function set($key, $value, $expireResolution = null, $expireTTL = null, $flag = null)
    {
        return $this->command('set', [
            $key,
            $value,
            $expireResolution ? [$expireResolution, $flag => $expireTTL] : null,
        ]);
    }

    /**
     * Removes the first count occurrences of the value element from the list.
     *
     * @param string $key
     * @param int    $count
     * @param        $value $value
     *
     * @return false|int
     */
    public function lrem($key, $count, $value)
    {
        return $this->command('lrem', [$key, $value, $count]);
    }

    /**
     * Removes and returns a random element from the set value at key.
     *
     * @param string   $key
     * @param int|null $count
     *
     * @return false|mixed
     */
    public function spop($key, $count = null)
    {
        return $this->command('spop', [$key]);
    }

    /**
     * Add one or more members to a sorted set or update its score if it already exists.
     *
     * @param string $key
     *
     * @return int
     */
    public function zadd($key, ...$dictionary)
    {
        if (1 === \count($dictionary)) {
            $_dictionary = [];

            foreach ($dictionary[0] as $member => $score) {
                $_dictionary[] = $score;
                $_dictionary[] = $member;
            }

            $dictionary = $_dictionary;
        }

        return $this->client->zadd($key, ...$dictionary);
    }

    /**
     * Execute commands in a pipeline.
     *
     * @return array|\Redis
     */
    public function pipeline(?callable $callback = null)
    {
        $pipeline = $this->client()->pipeline();

        return null === $callback
            ? $pipeline
            : tap($pipeline, $callback)->exec();
    }

    /**
     * Execute commands in a transaction.
     *
     * @return array|\Redis
     */
    public function transaction(?callable $callback = null)
    {
        $transaction = $this->client()->multi();

        return null === $callback
            ? $transaction
            : tap($transaction, $callback)->exec();
    }

    /**
     * Evaluate a LUA script serverside, from the SHA1 hash of the script instead of the script itself.
     *
     * @param string $script
     * @param int    $numkeys
     */
    public function evalsha($script, $numkeys, ...$arguments)
    {
        return $this->command('evalsha', [
            $this->script('load', $script), $arguments, $numkeys,
        ]);
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     */
    public function subscribe($channels, \Closure $callback): void
    {
        $this->client->subscribe((array)$channels, static function ($redis, $channel, $message) use ($callback): void {
            $callback($message, $channel);
        });
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     *
     * @param array|string $channels
     */
    public function psubscribe($channels, \Closure $callback): void
    {
        $this->client->psubscribe((array)$channels, static function ($redis, $pattern, $channel, $message) use ($callback): void {
            $callback($message, $channel);
        });
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     * @param string       $method
     */
    public function createSubscription($channels, \Closure $callback, $method = 'subscribe'): void
    {
    }

    /**
     * Execute a raw command.
     */
    public function executeRaw(array $parameters)
    {
        return $this->command('rawCommand', $parameters);
    }

    /**
     * Disconnects from the Redis instance.
     */
    public function disconnect(): void
    {
        $this->client->close();
    }

    /**
     * Proxy a call to the eval function of PhpRedis.
     */
    protected function proxyToEval(array $parameters)
    {
        return $this->command('eval', [
            $parameters[0] ?? null,
            \array_slice($parameters, 2),
            $parameters[1] ?? null,
        ]);
    }
}
