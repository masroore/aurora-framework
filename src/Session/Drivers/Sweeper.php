<?php

namespace Aurora\Session\Drivers;

interface Sweeper
{
    /**
     * Delete all expired sessions from persistent storage.
     *
     * @param int $expiration
     */
    public function sweep($expiration): void;
}
