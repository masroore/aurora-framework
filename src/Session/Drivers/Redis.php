<?php

namespace Aurora\Session\Drivers;

class Redis extends Driver
{
    /**
     * The Redis cache driver instance.
     *
     * @var Aurora\Cache\Drivers\Redis
     */
    protected $redis;

    /**
     * Create a new Redis session driver.
     *
     * @param Aurora\Cache\Drivers\Redis $redis
     */
    public function __construct(\Aurora\Cache\Drivers\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Load a session from storage by a given ID.
     *
     * If no session is found for the ID, null will be returned.
     *
     * @param string $id
     *
     * @return array
     */
    public function load($id)
    {
        return $this->redis->get($id);
    }

    /**
     * Save a given session to storage.
     *
     * @param array $session
     * @param array $config
     * @param bool  $exists
     */
    public function save($session, $config, $exists): void
    {
        $this->redis->put($session['id'], $session, $config['lifetime']);
    }

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    public function delete($id): void
    {
        $this->redis->forget($id);
    }
}
