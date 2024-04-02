<?php

namespace Aurora\Socialite;

/**
 * Class AccessToken.
 */
class AccessToken implements AccessTokenInterface, \ArrayAccess, \JsonSerializable
{
    use HasAttributes;

    /**
     * AccessToken constructor.
     */
    public function __construct(array $attributes)
    {
        if (empty($attributes['access_token'])) {
            throw new \InvalidArgumentException('The key "access_token" could not be empty.');
        }

        $this->attributes = $attributes;
    }

    public function __toString()
    {
        return (string)$this->getAttribute('access_token', '');
    }

    /**
     * Return the access token string.
     */
    public function getToken(): string
    {
        return $this->getAttribute('access_token');
    }

    /**
     * Return the refresh token string.
     */
    public function getRefreshToken(): string
    {
        return $this->getAttribute('refresh_token');
    }

    /**
     * Set refresh token into this object.
     */
    public function setRefreshToken(string $token): void
    {
        $this->setAttribute('refresh_token', $token);
    }

    public function jsonSerialize(): string
    {
        return $this->getToken();
    }
}
