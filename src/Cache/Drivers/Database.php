<?php

namespace Aurora\Cache\Drivers;

use Aurora\Config;
use Aurora\Database as DB;

class Database extends Driver
{
    /**
     * The cache key from the cache configuration file.
     *
     * @var string
     */
    protected $key;

    /**
     * Create a new database cache driver instance.
     *
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return null !== $this->get($key);
    }

    /**
     * Write an item to the cache for five years.
     *
     * @param string $key
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 2628000);
    }

    /**
     * Write an item to the cache for a given number of minutes.
     *
     * <code>
     *        // Put an item in the cache for 15 minutes
     *        Cache::put('name', 'Taylor', 15);
     * </code>
     *
     * @param string $key
     * @param int    $minutes
     */
    public function put($key, $value, $minutes): void
    {
        $key = $this->key . $key;

        $value = serialize($value);

        $expiration = $this->expiration($minutes);

        // To update the value, we'll first attempt an insert against the
        // database and if we catch an exception we'll assume that the
        // primary key already exists in the table and update.
        try {
            $this->table()->insert(compact('key', 'value', 'expiration'));
        } catch (\Exception $e) {
            $this->table()->where('key', '=', $key)->update(compact('value', 'expiration'));
        }
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): void
    {
        $this->table()->where('key', '=', $this->key . $key)->delete();
    }

    /**
     * Retrieve an item from the cache driver.
     *
     * @param string $key
     */
    protected function retrieve($key)
    {
        $cache = $this->table()->where('key', '=', $this->key . $key)->first();

        if (null !== $cache) {
            if (time() >= $cache->expiration) {
                return $this->forget($key);
            }

            return unserialize($cache->value);
        }
    }

    /**
     * Get a query builder for the database table.
     *
     * @return DB\Query
     */
    protected function table()
    {
        $connection = DB::connection(Config::get('cache.database.connection'));

        return $connection->table(Config::get('cache.database.table'));
    }
}
