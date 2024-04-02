<?php

namespace Aurora;

class Cookie
{
    /**
     * How long is forever (in minutes)?
     *
     * @var int
     */
    public const forever = 2628000;

    /**
     * The cookies that have been set.
     *
     * @var array
     */
    public static $jar = [];

    /**
     * Determine if a cookie exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public static function has($name)
    {
        return null !== static::get($name);
    }

    /**
     * Get the value of a cookie.
     *
     * <code>
     *        // Get the value of the "favorite" cookie
     *        $favorite = Cookie::get('favorite');
     *
     *        // Get the value of a cookie or return a default value
     *        $favorite = Cookie::get('framework', 'Aurora');
     * </code>
     *
     * @param string     $name
     * @param mixed|null $default
     *
     * @return string
     */
    public static function get($name, $default = null)
    {
        if (isset(static::$jar[$name])) {
            return static::parse(static::$jar[$name]['value']);
        }

        if (null !== ($value = Request::foundation()->cookies->get($name))) {
            return static::parse($value);
        }

        return value($default);
    }

    /**
     * Hash the given cookie value.
     */
    public static function hash(?string $value): string
    {
        return hash_hmac('sha1', $value ?? '', Config::get('app.key'));
    }

    /**
     * Set a "permanent" cookie. The cookie will last for one year.
     *
     * <code>
     *        // Set a cookie that should last one year
     *        Cookie::forever('favorite', 'Blue');
     * </code>
     *
     * @param string      $name
     * @param string      $value
     * @param string      $path
     * @param string      $domain
     * @param bool|null   $secure
     * @param bool        $httpOnly
     * @param bool        $raw
     * @param string|null $sameSite
     */
    public static function forever($name, $value, $path = '/', $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null): void
    {
        static::put($name, $value, static::forever, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * Set the value of a cookie.
     *
     * <code>
     *        // Set the value of the "favorite" cookie
     *        Cookie::put('favorite', 'Aurora');
     *
     *        // Set the value of the "favorite" cookie for twenty minutes
     *        Cookie::put('favorite', 'Aurora', 20);
     * </code>
     *
     * @param string      $name
     * @param string      $value
     * @param int         $expiration
     * @param string      $path
     * @param string      $domain
     * @param bool|null   $secure
     * @param bool        $httpOnly
     * @param bool        $raw
     * @param string|null $sameSite
     */
    public static function put($name, $value, $expiration = 0, $path = '/', $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null): void
    {
        if (0 !== $expiration) {
            $expiration = time() + ($expiration * 60);
        }

        $value = static::hash($value) . '+' . $value;

        // If the developer has explicitly disabled SLL, then we shouldn't force
        // this cookie over SSL.
        $secure = $secure && Config::get('app.ssl');

        // If the secure option is set to true, yet the request is not over HTTPS
        // we'll throw an exception to let the developer know that they are
        // attempting to send a secure cookie over the insecure HTTP.
        if ($secure && !Request::secure()) {
            throw new \Exception('Attempting to set secure cookie over HTTP.');
        }

        static::$jar[$name] = compact('name', 'value', 'expiration', 'path', 'domain', 'secure', 'httpOnly', 'raw', 'sameSite');
    }

    /**
     * Delete a cookie.
     *
     * @param string     $name
     * @param string     $path
     * @param string     $domain
     * @param bool       $secure
     * @param mixed|null $sameSite
     */
    public static function forget($name, $path = '/', $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null): void
    {
        static::put($name, null, -2000, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * Parse a hash fingerprinted cookie value.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function parse($value)
    {
        $segments = explode('+', $value);

        // First we will make sure the cookie actually has enough segments to even
        // be valid as being set by the application. If it does not we will go
        // ahead and throw exceptions now since there the cookie is invalid.
        if (!(\count($segments) >= 2)) {
            return;
        }

        $value = implode('+', \array_slice($segments, 1));

        // Now we will check if the SHA-1 hash present in the first segment matches
        // the ShA-1 hash of the rest of the cookie value, since the hash should
        // have been set when the cookie was first created by the application.
        if ($segments[0] === static::hash($value)) {
            return $value;
        }
    }
}
