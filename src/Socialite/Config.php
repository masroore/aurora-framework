<?php

namespace Aurora\Socialite;

class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Config constructor.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return (bool)$this->get($key);
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public function get($key, $default = null)
    {
        $config = $this->config;

        if (null === $key) {
            return $config;
        }

        if (isset($config[$key])) {
            return $config[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($config) || !\array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * Whether a offset exists.
     *
     * @see  http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
     * @return bool true on success or false on failure.
     *              </p>
     *              <p>
     *              The return value will be casted to boolean if non-boolean was returned
     *
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->config);
    }

    /**
     * Offset to retrieve.
     *
     * @see  http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     *                      </p>
     *
     * @return mixed Can return all value types
     *
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Offset to set.
     *
     * @see  http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @since 5.0.0
     */
    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * @param string $key
     *
     * @return array
     */
    public function set($key, $value)
    {
        if (null === $key) {
            throw new \InvalidArgumentException('Invalid config key.');
        }

        $keys = explode('.', $key);
        $config = &$this->config;

        while (\count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($config[$key]) || !\is_array($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }

        $config[array_shift($keys)] = $value;

        return $config;
    }

    /**
     * Offset to unset.
     *
     * @see  http://php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset): void
    {
        $this->set($offset, null);
    }
}
