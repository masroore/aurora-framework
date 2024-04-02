<?php

namespace Aurora\Cache\Drivers;

abstract class Sectionable extends Driver
{
    /**
     * Indicates that section caching is implicit based on keys.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * The implicit section key delimiter.
     *
     * @var string
     */
    public $delimiter = '::';

    /**
     * Retrieve a sectioned item from the cache driver.
     *
     * @param string     $section
     * @param string     $key
     * @param mixed|null $default
     */
    public function get_from_section($section, $key, $default = null)
    {
        return $this->get($this->section_item_key($section, $key), $default);
    }

    /**
     * Write a sectioned item to the cache.
     *
     * @param string $section
     * @param string $key
     * @param int    $minutes
     */
    public function put_in_section($section, $key, $value, $minutes): void
    {
        $this->put($this->section_item_key($section, $key), $value, $minutes);
    }

    /**
     * Write a sectioned item to the cache that lasts forever.
     *
     * @param string $section
     * @param string $key
     */
    public function forever_in_section($section, $key, $value)
    {
        return $this->forever($this->section_item_key($section, $key), $value);
    }

    /**
     * Get a sectioned item from the cache, or cache and return the default value.
     *
     * @param string $section
     * @param string $key
     * @param int    $minutes
     * @param string $function
     */
    public function remember_in_section($section, $key, $default, $minutes, $function = 'put')
    {
        $key = $this->section_item_key($section, $key);

        return $this->remember($key, $default, $minutes, $function);
    }

    /**
     * Get a sectioned item from the cache, or cache the default value forever.
     *
     * @param string $section
     * @param string $key
     */
    public function sear_in_section($section, $key, $default)
    {
        return $this->sear($this->section_item_key($section, $key), $default);
    }

    /**
     * Delete a sectioned item from the cache.
     *
     * @param string $section
     * @param string $key
     */
    public function forget_in_section($section, $key)
    {
        return $this->forget($this->section_item_key($section, $key));
    }

    /**
     * Delete an entire section from the cache.
     *
     * @param string $section
     *
     * @return bool|int
     */
    abstract public function forget_section($section);

    /**
     * Indicates if a key is sectionable.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function sectionable($key)
    {
        return $this->implicit && $this->sectioned($key);
    }

    /**
     * Determine if a key is sectioned.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function sectioned($key)
    {
        return str_contains($key, '::');
    }

    /**
     * Get the section and key from a sectioned key.
     *
     * @param string $key
     *
     * @return array
     */
    protected function parse($key)
    {
        return explode('::', $key, 2);
    }
}
