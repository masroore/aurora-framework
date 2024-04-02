<?php

namespace Aurora\Session\Drivers;

use Aurora\Cookie as C;
use Aurora\Crypt;
use Aurora\Traits\SerializerTrait;

class Cookie extends Driver
{
    use SerializerTrait;

    /**
     * The name of the cookie used to store the session payload.
     *
     * @var string
     */
    public const payload = 'session_payload';

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
        if (C::has(self::payload)) {
            return (array)$this->unserialize(Crypt::decrypt(C::get(self::payload)));
        }
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
        extract($config, \EXTR_SKIP);

        $payload = Crypt::encrypt($this->serialize($session));

        C::put(self::payload, $payload, $lifetime, $path, $domain, $secure);
    }

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    public function delete($id): void
    {
        C::forget(self::payload);
    }
}
