<?php

namespace Aurora\Socialite;

class User implements \ArrayAccess, \JsonSerializable, \Serializable, UserInterface
{
    use HasAttributes;

    /**
     * User constructor.
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the username for the user.
     */
    public function getUsername(): string
    {
        return $this->getAttribute('username', $this->getId());
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getId(): string
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the nickname / username for the user.
     */
    public function getNickname(): string
    {
        return $this->getAttribute('nickname');
    }

    /**
     * Get the full name of the user.
     */
    public function getName(): string
    {
        return $this->getAttribute('name');
    }

    /**
     * Get the e-mail address of the user.
     */
    public function getEmail(): string
    {
        return $this->getAttribute('email');
    }

    /**
     * Get the avatar / image URL for the user.
     */
    public function getAvatar(): string
    {
        return $this->getAttribute('avatar');
    }

    /**
     * Set the token on the user.
     *
     * @return $this
     */
    public function setToken(AccessTokenInterface $token): self
    {
        $this->setAttribute('token', $token);

        return $this;
    }

    /**
     * @return $this
     */
    public function setProviderName(string $provider): self
    {
        $this->setAttribute('provider', $provider);

        return $this;
    }

    public function getProviderName(): string
    {
        return $this->getAttribute('provider');
    }

    /**
     * Get user access token.
     */
    public function getAccessToken(): string
    {
        return $this->getToken()->getToken();
    }

    /**
     * Get the authorized token.
     */
    public function getToken(): AccessToken
    {
        return $this->getAttribute('token');
    }

    /**
     * Get user refresh token.
     */
    public function getRefreshToken(): string
    {
        return $this->getToken()->getRefreshToken();
    }

    /**
     * Get the original attributes.
     */
    public function getOriginal(): array
    {
        return $this->getAttribute('original');
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function serialize(): string
    {
        return serialize($this->attributes);
    }

    /**
     * Constructs the object.
     *
     * @see  https://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized the string representation of the object
     */
    public function unserialize($serialized): void
    {
        $this->attributes = unserialize($serialized) ?: [];
    }
}
