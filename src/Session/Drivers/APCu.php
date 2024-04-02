<?php

namespace Aurora\Session\Drivers;

class APCu extends Driver
{
    /**
     * The APC cache driver instance.
     *
     * @var Aurora\Cache\Drivers\APCu
     */
    private $apc;

    /**
     * Create a new APC session driver instance.
     *
     * @param Aurora\Cache\Drivers\APCu $apc
     */
    public function __construct(\Aurora\Cache\Drivers\APCu $apc)
    {
        $this->apc = $apc;
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
        return $this->apc->get($id);
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
        $this->apc->put($session['id'], $session, $config['lifetime']);
    }

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    public function delete($id): void
    {
        $this->apc->forget($id);
    }
}
