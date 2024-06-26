<?php

namespace Aurora;

class Fluent
{
    /**
     * All of the attributes set on the fluent container.
     *
     * @var array
     */
    public $attributes = [];

    /**
     * Create a new fluent container instance.
     *
     * <code>
     *        Create a new fluent container with attributes
     *        $fluent = new Fluent(array('name' => 'Taylor'));
     * </code>
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Handle dynamic calls to the container to set attributes.
     *
     * <code>
     *        // Fluently set the value of a few attributes
     *        $fluent->name('Taylor')->age(25);
     *
     *        // Set the value of an attribute to true (boolean)
     *        $fluent->nullable()->name('Taylor');
     * </code>
     */
    public function __call($method, $parameters)
    {
        $this->$method = (\count($parameters) > 0) ? $parameters[0] : true;

        return $this;
    }

    /**
     * Dynamically retrieve the value of an attribute.
     */
    public function __get($key)
    {
        if (\array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Dynamically set the value of an attribute.
     */
    public function __set($key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if an attribute is set.
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset an attribute.
     */
    public function __unset($key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Get an attribute from the fluent container.
     *
     * @param string     $attribute
     * @param mixed|null $default
     */
    public function get($attribute, $default = null)
    {
        return array_get($this->attributes, $attribute, $default);
    }
}
