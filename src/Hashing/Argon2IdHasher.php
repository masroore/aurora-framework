<?php

namespace Aurora\Hashing;

class Argon2IdHasher extends ArgonHasher
{
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
        if ($this->verifyAlgorithm && 'argon2id' !== $this->info($hashedValue)['algoName']) {
            throw new \RuntimeException('This password does not use the Argon2id algorithm.');
        }

        if (0 === mb_strlen($hashedValue)) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Get the algorithm that should be used for hashing.
     *
     * @return int
     */
    protected function algorithm()
    {
        return \PASSWORD_ARGON2ID;
    }
}
