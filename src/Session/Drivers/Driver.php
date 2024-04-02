<?php

namespace Aurora\Session\Drivers;

use Aurora\Str;

abstract class Driver
{
    /**
     * Save a given session to storage.
     *
     * @param array $session
     * @param array $config
     * @param bool  $exists
     */
    abstract public function save($session, $config, $exists): void;

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    abstract public function delete($id): void;

    /**
     * Create a fresh session array with a unique ID.
     *
     * @return array
     */
    public function fresh()
    {
        // We will simply generate an empty session payload array, using an ID
        // that is not currently assigned to any existing session within the
        // application and return it to the driver.
        return ['id' => $this->id(), 'data' => [
            ':new:' => [],
            ':old:' => [],
        ]];
    }

    /**
     * Get a new session ID that isn't assigned to any current session.
     *
     * @return string
     */
    public function id()
    {
        // If the driver is an instance of the Cookie driver, we are able to
        // just return any string since the Cookie driver has no real idea
        // of a server side persisted session with an ID.
        if ($this instanceof Cookie) {
            return mb_strtolower(Str::random(40));
        }

        $session = null;

        // We'll continue generating random IDs until we find an ID that is
        // not currently assigned to a session. This is almost definitely
        // going to happen on the first iteration.
        do {
            $session = $this->load($id = mb_strtolower(Str::random(40)));
        } while (null !== $session);

        return $id;
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
    abstract public function load($id);
}
