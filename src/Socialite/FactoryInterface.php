<?php

namespace Aurora\Socialite;

interface FactoryInterface
{
    /**
     * Get an OAuth provider implementation.
     */
    public function driver(string $driver): ProviderInterface;
}
