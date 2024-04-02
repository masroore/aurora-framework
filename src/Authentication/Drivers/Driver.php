<?php

namespace Aurora\Authentication\Drivers;

use Aurora\Config;
use Aurora\Cookie;
use Aurora\Crypt;
use Aurora\Event;
use Aurora\Session;
use Aurora\Str;

abstract class Driver
{
    /**
     * The user currently being managed by the driver.
     */
    public mixed $user;

    /**
     * The current value of the user's token.
     */
    public ?string $token;

    /**
     * Create a new login auth driver instance.
     */
    public function __construct()
    {
        if (Session::started()) {
            $this->token = Session::get($this->token());
        }

        // If a token did not exist in the session for the user, we will attempt
        // to load the value of a "remember me" cookie for the driver, which
        // serves as a long-lived client side authenticator for the user.
        if (null === $this->token) {
            $this->token = $this->recall();
        }
    }

    /**
     * Determine if the user of the application is not logged in.
     *
     * This method is the inverse of the "check" method.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Determine if the user is logged in.
     */
    public function check(): bool
    {
        return null !== $this->user();
    }

    /**
     * Get the current user of the application.
     *
     * If the user is a guest, null should be returned.
     */
    public function user(): mixed
    {
        if (null !== $this->user) {
            return $this->user;
        }

        return $this->user = $this->retrieve($this->token);
    }

    /**
     * Get the given application user by ID.
     */
    abstract public function retrieve(int $id): mixed;

    /**
     * Attempt to log a user into the application.
     */
    abstract public function attempt(array $arguments = []): bool;

    /**
     * Login the user assigned to the given token.
     *
     * The token is typically a numeric ID for the user.
     */
    public function login(string $token, bool $remember = false): bool
    {
        $this->token = $token;

        $this->store($token);

        if ($remember) {
            $this->remember($token);
        }

        Event::fire('aurora.auth: login');

        return true;
    }

    /**
     * Log the user out of the driver's auth context.
     */
    public function logout(): void
    {
        $this->user = null;

        $this->cookie($this->recaller(), null, -2000);

        Session::forget($this->token());

        Event::fire('aurora.auth: logout');

        $this->token = null;
    }

    /**
     * Get the session key name used to store the token.
     */
    protected function token(): string
    {
        return $this->name() . '_login';
    }

    /**
     * Get the name of the driver in a storage friendly format.
     */
    protected function name(): string
    {
        return mb_strtolower(str_replace('\\', '_', static::class));
    }

    /**
     * Attempt to find a "remember me" cookie for the user.
     */
    protected function recall(): ?string
    {
        $cookie = Cookie::get($this->recaller());

        // By default, "remember me" cookies are encrypted and contain the user
        // token as well as a random string. If it exists, we'll decrypt it
        // and return the first segment, which is the user's ID token.
        if (null !== $cookie) {
            return head(explode('|', Crypt::decrypt($cookie)));
        }

        return null;
    }

    /**
     * Get the name used for the "remember me" cookie.
     */
    protected function recaller(): string
    {
        return Config::get('auth.cookie', $this->name() . '_remember');
    }

    /**
     * Store a user's token in the session.
     */
    protected function store(string $token): void
    {
        Session::put($this->token(), $token);
    }

    /**
     * Store a user's token in a long-lived cookie.
     */
    protected function remember(string $token): void
    {
        $token = Crypt::encrypt($token . '|' . Str::random(40));

        $this->cookie($this->recaller(), $token, Cookie::forever);
    }

    /**
     * Store an authentication cookie.
     */
    protected function cookie(string $name, string $value, int $minutes): void
    {
        // When setting the default implementation of an authentication
        // cookie we'll use the same settings as the session cookie.
        // This typically makes sense as they both are sensitive.
        $config = Config::get('session');

        extract($config, \EXTR_OVERWRITE);

        Cookie::put($name, $value, $minutes, $path, $domain, $secure);
    }
}
