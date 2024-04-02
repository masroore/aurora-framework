<?php

namespace Aurora\ResponseCache\Drivers;

use Aurora\Traits\SerializerTrait;

class File extends Driver
{
    use SerializerTrait;
    public const CACHE_EXT = '.dat';
    public const CACHE_PREFIX = 'c_';

    /**
     * The path to which the cache files should be written.
     *
     * @var string
     */
    protected $path;

    /**
     * Create a new File cache driver instance.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
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
        return file_exists($this->path($key));
    }

    /**
     * Write an item to the cache for five years.
     *
     * @param string $key
     */
    public function forever($key, $value): void
    {
        $this->put($key, $value, 2628000);
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
        if ($minutes <= 0) {
            return;
        }

        $value = $this->pickle($value, $minutes);
        file_put_contents($this->path($key), $value, \LOCK_EX);
    }

    /**
     * Flush the entire cache.
     */
    public function flush(): void
    {
        array_map('unlink', glob($this->path . '*'));
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        if (file_exists($this->path($key))) {
            @unlink($this->path($key));
        }
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    protected function retrieve($key)
    {
        if (!file_exists($this->path($key))) {
            return;
        }

        // File based caches store have the expiration timestamp stored in
        // UNIX format prepended to their contents. We'll compare the
        // timestamp to the current time when we read the file.
        [$exp, $cache] = $this->unpickle(file_get_contents($this->path($key)));

        if (time() >= $exp) {
            $this->forget($key);

            return;
        }

        return $cache;
    }

    private function pickle($value, $minutes)
    {
        if ($this->isIgBinarySupported()) {
            $payload = ['e' => $this->expiration($minutes), 'd' => $value];

            return $this->serialize($payload);
        }

        return $this->expiration($minutes) . $this->serialize($value);
    }

    private function unpickle($content)
    {
        if ($this->isIgBinarySupported()) {
            $payload = $this->unserialize($content);

            return [$payload['e'], $payload['d']];
        }

        $exp = mb_substr($content, 0, 10);
        $data = $this->unserialize(mb_substr($content, 10));

        return [$exp, $data];
    }

    /**
     * Get file path from the cache folder.
     *
     * @param string $key
     *
     * @return string
     */
    private function path($key)
    {
        $prefix = null !== $this->getPrefix() ? $this->getPrefix() : self::CACHE_PREFIX;
        $suffix = null !== $this->getSuffix() ? $this->getSuffix() : self::CACHE_EXT;

        return $this->path . $prefix . sha1($key) . $suffix;
    }
}
