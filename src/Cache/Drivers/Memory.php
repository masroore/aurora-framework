<?php

namespace Aurora\Cache\Drivers;

class Memory extends Sectionable
{
    /**
     * The in-memory array of cached items.
     *
     * @var string
     */
    public $storage = [];

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
    public function forever($key, $value): void
    {
        if ($this->sectionable($key)) {
            [$section, $key] = $this->parse($key);

            $this->forever_in_section($section, $key, $value);
        } else {
            $this->put($key, $value, 0);
        }
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
        if ($this->sectionable($key)) {
            [$section, $key] = $this->parse($key);

            $this->put_in_section($section, $key, $value, $minutes);
        } else {
            array_set($this->storage, $key, $value);
        }
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        if ($this->sectionable($key)) {
            [$section, $key] = $this->parse($key);

            if ('*' === $key) {
                $this->forget_section($section);
            } else {
                $this->forget_in_section($section, $key);
            }
        } else {
            array_forget($this->storage, $key);
        }
    }

    /**
     * Delete an entire section from the cache.
     *
     * @param string $section
     */
    public function forget_section($section): void
    {
        array_forget($this->storage, 'section#' . $section);
    }

    /**
     * Flush the entire cache.
     */
    public function flush(): void
    {
        $this->storage = [];
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    protected function retrieve($key)
    {
        if ($this->sectionable($key)) {
            [$section, $key] = $this->parse($key);

            return $this->get_from_section($section, $key);
        }

        return array_get($this->storage, $key);
    }

    /**
     * Get a section item key for a given section and key.
     *
     * @param string $section
     * @param string $key
     *
     * @return string
     */
    protected function section_item_key($section, $key)
    {
        return "section#{$section}.{$key}";
    }
}
