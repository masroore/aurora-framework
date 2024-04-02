<?php

namespace Aurora;

use Aurora\Traits\SerializerTrait;

class Options implements \ArrayAccess, \Countable
{
    use SerializerTrait;

    /** @var string */
    protected $fileName;

    /**
     * @param string $fileName
     * @param ?array $values
     *
     * @return $this
     */
    public static function make($fileName, ?array $values = null)
    {
        $instance = (new static())->setFileName($fileName);

        if (null !== $values) {
            $instance->put($values);
        }

        return $instance;
    }

    /**
     * Put a value in the store.
     *
     * @param array|string    $name
     * @param int|string|null $value
     *
     * @return $this
     */
    public function put($name, $value = null)
    {
        if ([] === $name) {
            return $this;
        }

        $newValues = $name;

        if (!\is_array($name)) {
            $newValues = [$name => $value];
        }

        $newContent = array_merge($this->all(), $newValues);

        $this->setContent($newContent);

        return $this;
    }

    /**
     * Push a new value into an array.
     *
     * @param string $name
     *
     * @return $this
     */
    public function push($name, $pushValue)
    {
        if (!\is_array($pushValue)) {
            $pushValue = [$pushValue];
        }

        if (!$this->has($name)) {
            $this->put($name, $pushValue);

            return $this;
        }

        $oldValue = $this->get($name);

        if (!\is_array($oldValue)) {
            $oldValue = [$oldValue];
        }

        $newValue = array_merge($oldValue, $pushValue);

        $this->put($name, $newValue);

        return $this;
    }

    /**
     * Prepend a new value in an array.
     *
     * @param string $name
     *
     * @return $this
     */
    public function prepend($name, $prependValue)
    {
        if (!\is_array($prependValue)) {
            $prependValue = [$prependValue];
        }

        if (!$this->has($name)) {
            $this->put($name, $prependValue);

            return $this;
        }

        $oldValue = $this->get($name);

        if (!\is_array($oldValue)) {
            $oldValue = [$oldValue];
        }

        $newValue = array_merge($prependValue, $oldValue);

        $this->put($name, $newValue);

        return $this;
    }

    /**
     * Get a value from the store.
     *
     * @param string $name
     *
     * @return array|string|null
     */
    public function get($name, $default = null)
    {
        $all = $this->all();

        if (!\array_key_exists($name, $all)) {
            return $default;
        }

        return $all[$name];
    }

    // Determine if the store has a value for the given name.

    /**
     * @return bool
     */
    public function has($name)
    {
        return \array_key_exists($name, $this->all());
    }

    /**
     * Get all values from the store.
     *
     * @return array
     */
    public function all()
    {
        if (!file_exists($this->fileName)) {
            return [];
        }

        return $this->load(file_get_contents($this->fileName));
    }

    /**
     * Get all keys starting with a given string from the store.
     *
     * @param string $startingWith
     *
     * @return array
     */
    public function allStartingWith($startingWith = '')
    {
        $values = $this->all();

        if ('' === $startingWith) {
            return $values;
        }

        return $this->filterKeysStartingWith($values, $startingWith);
    }

    /**
     * Forget a value from the store.
     *
     * @param string $key
     *
     * @return $this
     */
    public function forget($key)
    {
        $newContent = $this->all();

        unset($newContent[$key]);

        $this->setContent($newContent);

        return $this;
    }

    /**
     * Flush all values from the store.
     *
     * @return $this
     */
    public function flush()
    {
        return $this->setContent([]);
    }

    /**
     * Flush all values which keys start with a given string.
     *
     * @param string $startingWith
     *
     * @return $this
     */
    public function flushStartingWith($startingWith = '')
    {
        $newContent = [];

        if ('' !== $startingWith) {
            $newContent = $this->filterKeysNotStartingWith($this->all(), $startingWith);
        }

        return $this->setContent($newContent);
    }

    /**
     * Get and forget a value from the store.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function pull($name)
    {
        $value = $this->get($name);

        $this->forget($name);

        return $value;
    }

    /**
     * Increment a value from the store.
     *
     * @param string $name
     * @param int    $by
     *
     * @return int|string|null
     */
    public function increment($name, $by = 1)
    {
        $currentValue = null !== $this->get($name) ? $this->get($name) : 0;

        $newValue = $currentValue + $by;

        $this->put($name, $newValue);

        return $newValue;
    }

    /**
     * Decrement a value from the store.
     *
     * @param string $name
     * @param int    $by
     *
     * @return int|string|null
     */
    public function decrement($name, $by = 1)
    {
        return $this->increment($name, $by * -1);
    }

    /**
     * Whether a offset exists.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Offset to retrieve.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Offset to set.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value): void
    {
        $this->put($offset, $value);
    }

    /**
     * Offset to unset.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset): void
    {
        $this->forget($offset);
    }

    /**
     * Count elements.
     *
     * @see http://php.net/manual/en/countable.count.php
     *
     * @return int
     */
    public function count()
    {
        return \count($this->all());
    }

    /**
     * Set the filename where all values will be stored.
     *
     * @param string $fileName
     *
     * @return $this
     */
    protected function setFileName($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * @param string $startsWith
     *
     * @return array
     */
    protected function filterKeysStartingWith(array $values, $startsWith)
    {
        return array_filter($values, fn ($key) => $this->startsWith($key, $startsWith), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $startsWith
     *
     * @return array
     */
    protected function filterKeysNotStartingWith(array $values, $startsWith)
    {
        return array_filter($values, fn ($key) => !$this->startsWith($key, $startsWith), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    protected function startsWith($haystack, $needle)
    {
        return 0 === mb_strpos($haystack, $needle);
    }

    /**
     * @return $this
     */
    protected function setContent(array $values)
    {
        file_put_contents($this->fileName, $this->dump($values));

        if (!\count($values)) {
            unlink($this->fileName);
        }

        return $this;
    }
}
