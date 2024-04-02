<?php

namespace Aurora\Cache\Drivers;

use Aurora\Redis\RedisManager;
use Aurora\Traits\SerializerTrait;

class Redis extends Driver
{
    use SerializerTrait;

    /**
     * The Redis database instance.
     *
     * @var RedisManager
     */
    protected $redis;

    /**
     * Create a new Redis cache driver instance.
     */
    public function __construct(RedisManager $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return null !== $this->redis->get($key);
    }

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
    public function put($key, $value, $minutes): void
    {
        $this->forever($key, $value);

        $this->redis->expire($key, $minutes * 60);
    }

    /**
     * Write an item to the cache that lasts forever.
     *
     * @param string $key
     */
    public function forever($key, $value): void
    {
        $this->redis->set($key, $this->serialize($value));
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        $this->redis->del($key);
    }

    /**
     * Flush the entire cache.
     */
    public function flush(): void
    {
        $this->redis->flushdb();
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    protected function retrieve($key)
    {
        if (null !== ($cache = $this->redis->get($key))) {
            return $this->unserialize($cache);
        }
    }
}
