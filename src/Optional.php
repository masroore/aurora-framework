<?php

namespace Aurora;

class Optional implements \ArrayAccess
{
    /**
     * The underlying object.
     */
    protected $value;

    /**
     * Create a new optional instance.
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Dynamically access a property on the underlying object.
     *
     * @param string $key
     */
    public function __get($key)
    {
        if (\is_object($this->value)) {
            return $this->value->{$key} ?? null;
        }
    }

    public function __set($key, $value): void
    {
    }

    public function __isset($key)
    {
        return \is_object($this->value) ? isset($this->value->{$key}) : false;
    }

    /**
     * Dynamically pass a method to the underlying object.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __call($method, $parameters)
    {
        if (\is_object($this->value)) {
            return $this->value->{$method}(...$parameters);
        }
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return Arr::accessible($this->value) && Arr::exists($this->value, $key);
    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet($key)
    {
        return Arr::get($this->value, $key);
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet($key, $value): void
    {
        if (Arr::accessible($this->value)) {
            $this->value[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key): void
    {
        if (Arr::accessible($this->value)) {
            unset($this->value[$key]);
        }
    }
}
