<?php

namespace Aurora\Socialite;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface ProviderInterface
{
    /**
     * Redirect the user to the authentication page for the provider.
     */
    public function redirect(): RedirectResponse;

    /**
     * Get the User instance for the authenticated user.
     */
    public function user(?AccessTokenInterface $token = null): User;
}
