<?php

namespace Aurora\Socialite;

/**
 * Interface AccessTokenInterface.
 */
interface AccessTokenInterface
{
    /**
     * Return the access token string.
     */
    public function getToken(): string;
}
