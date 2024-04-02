<?php

namespace Aurora;

use Aurora\Hashing\Argon2IdHasher;
use Aurora\Hashing\ArgonHasher;
use Aurora\Hashing\BcryptHasher;
use Aurora\Hashing\Hasher;

class Hash
{
    /**
     * All of the active hash drivers.
     *
     * @var array
     */
    public static $drivers = [];

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     *
     * @return array
     */
    public static function info($hashedValue)
    {
        return self::driver()->info($hashedValue);
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     *
     * @return string
     */
    public static function make($value, array $options = [])
    {
        return self::driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     *
     * @return bool
     */
    public static function check($value, $hashedValue, array $options = [])
    {
        return self::driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     *
     * @return bool
     */
    public static function needsRehash($hashedValue, array $options = [])
    {
        return self::driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get a hash driver instance.
     *
     * If no driver name is specified, the default will be returned.
     *
     * <code>
     *        // Get the default hash driver instance
     *        $driver = Hash::driver();
     *
     *        // Get a specific hash driver instance by name
     *        $driver = Hash::driver('argon');
     * </code>
     *
     * @param string $driver
     *
     * @return Hasher
     */
    public static function driver($driver = null)
    {
        if (null === $driver) {
            $driver = Config::get('hashing.driver', 'bcrypt');
        }

        if (!isset(static::$drivers[$driver])) {
            static::$drivers[$driver] = static::factory($driver);
        }

        return static::$drivers[$driver];
    }

    /**
     * Create a new hash driver instance.
     *
     * @param string $driver
     *
     * @return Hasher
     */
    protected static function factory($driver)
    {
        switch ($driver) {
            case 'bcrypt':
                return new BcryptHasher(Config::get('hashing.bcrypt'));

            case 'argon':
                return new ArgonHasher(Config::get('hashing.argon'));

            case 'argon2id':
                return new Argon2IdHasher(Config::get('hashing.argon'));

            default:
                throw new \Exception("Cache driver {$driver} is not supported.");
        }
    }
}
