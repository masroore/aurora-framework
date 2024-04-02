<?php

namespace Aurora\Cache\Drivers;

class WinCache extends Driver
{
    /**
     * The cache key from the cache configuration file.
     *
     * @var string
     */
    protected $key;

    /**
     * Create a new WinCache cache driver instance.
     *
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
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
        return null !== $this->get($key);
    }

    /**
     * Write an item to the cache that lasts forever.
     *
     * @param string $key
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
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
        wincache_ucache_add($this->key . $key, $value, $minutes * 60);
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        wincache_ucache_delete($this->key . $key);
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    protected function retrieve($key)
    {
        if (false !== ($cache = wincache_ucache_get($this->key . $key))) {
            return $cache;
        }
    }
}
