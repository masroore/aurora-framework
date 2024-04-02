<?php

namespace Aurora\Socialite;

trait HasAttributes
{
    /**
     * @var array
     */
    protected $attributes = [];

    public function __get($property)
    {
        return $this->getAttribute($property);
    }

    /**
     * Return the extra attribute.
     */
    public function getAttribute(string $name, ?string $default = null): ?string
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Map the given array onto the user's properties.
     *
     * @return $this
     */
    public function merge(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->attributes);
    }

    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Set extra attributes.
     *
     * @return $this
     */
    public function setAttribute(string $name, $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Return array.
     */
    public function toArray(): array
    {
        return $this->getAttributes();
    }

    /**
     * Return the attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return JSON.
     */
    public function toJSON(): string
    {
        return json_encode($this->getAttributes(), \JSON_UNESCAPED_UNICODE);
    }
}
