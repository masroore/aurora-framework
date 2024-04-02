<?php

namespace Aurora\Hashing;

abstract class AbstractHasher implements Hasher
{
    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     *
     * @return array
     */
    public function info($hashedValue)
    {
        return password_get_info($hashedValue);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     *
     * @return bool
     */
    public function check($value, $hashedValue, array $options = [])
    {
        if (0 === mb_strlen($hashedValue)) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }
}
