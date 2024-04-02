<?php

namespace Aurora\Hashing;

class ArgonHasher extends AbstractHasher
{
    /**
     * The default memory cost factor.
     *
     * @var int
     */
    protected $memory = 1024;

    /**
     * The default time cost factor.
     *
     * @var int
     */
    protected $time = 2;

    /**
     * The default threads factor.
     *
     * @var int
     */
    protected $threads = 2;

    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected $verifyAlgorithm = false;

    /**
     * Create a new hasher instance.
     */
    public function __construct(array $options = [])
    {
        $this->time = array_get($options, 'time', $this->time);
        $this->memory = array_get($options, 'memory', $this->memory);
        $this->threads = array_get($options, 'threads', $this->threads);
        $this->verifyAlgorithm = array_get($options, 'verifyAlgorithm', $this->verifyAlgorithm);
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     *
     * @return string
     */
    public function make($value, array $options = [])
    {
        $hash = password_hash($value, $this->algorithm(), [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);

        if (false === $hash) {
            throw new \RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
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
        if ($this->verifyAlgorithm && 'argon2i' !== $this->info($hashedValue)['algoName']) {
            throw new \RuntimeException('This password does not use the Argon2i algorithm.');
        }

        return parent::check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     *
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = [])
    {
        return password_needs_rehash($hashedValue, $this->algorithm(), [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);
    }

    /**
     * Set the default password memory factor.
     *
     * @return $this
     */
    public function setMemory(int $memory)
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * Set the default password timing factor.
     *
     * @return $this
     */
    public function setTime(int $time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Set the default password threads factor.
     *
     * @return $this
     */
    public function setThreads(int $threads)
    {
        $this->threads = $threads;

        return $this;
    }

    /**
     * Get the algorithm that should be used for hashing.
     *
     * @return int
     */
    protected function algorithm()
    {
        return \PASSWORD_ARGON2I;
    }

    /**
     * Extract the memory cost value from the options array.
     *
     * @return int
     */
    protected function memory(array $options)
    {
        return array_get($options, 'memory', $this->memory);
    }

    /**
     * Extract the time cost value from the options array.
     *
     * @return int
     */
    protected function time(array $options)
    {
        return array_get($options, 'time', $this->time);
    }

    /**
     * Extract the threads value from the options array.
     *
     * @return int
     */
    protected function threads(array $options)
    {
        return array_get($options, 'threads', $this->threads);
    }
}
