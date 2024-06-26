<?php

namespace Aurora;

class Uri
{
    /**
     * The URI for the current request.
     *
     * @var string
     */
    public static $uri;

    /**
     * The URI segments for the current request.
     *
     * @var array
     */
    public static $segments = [];

    /**
     * Get the full URI including the query string.
     *
     * @return string
     */
    public static function full()
    {
        return Request::getUri();
    }

    /**
     * Determine if the current URI matches a given pattern.
     *
     * @param string $pattern
     *
     * @return bool
     */
    public static function is($pattern)
    {
        return Str::is($pattern, static::current());
    }

    /**
     * Get the URI for the current request.
     *
     * @return string
     */
    public static function current()
    {
        if (null !== static::$uri) {
            return static::$uri;
        }

        // We'll simply get the path info from the Symfony Request instance and then
        // format to meet our needs in the router. If the URI is root, we'll give
        // back a single slash, otherwise we'll strip all of the slashes off.
        $uri = static::format(Request::getPathInfo());

        static::segments($uri);

        return static::$uri = $uri;
    }

    /**
     * Get a specific segment of the request URI via a one-based index.
     *
     * <code>
     *        // Get the first segment of the request URI
     *        $segment = URI::segment(1);
     *
     *        // Get the second segment of the URI, or return a default value
     *        $segment = URI::segment(2, 'Taylor');
     * </code>
     *
     * @param int        $index
     * @param mixed|null $default
     *
     * @return string
     */
    public static function segment($index, $default = null)
    {
        static::current();

        return array_get(static::$segments, $index - 1, $default);
    }

    /**
     * Format a given URI.
     *
     * @param string $uri
     *
     * @return string
     */
    protected static function format($uri)
    {
        return trim($uri, '/') ?: '/';
    }

    /**
     * Set the URI segments for the request.
     *
     * @param string $uri
     */
    protected static function segments($uri): void
    {
        $segments = explode('/', trim($uri, '/'));

        static::$segments = array_diff($segments, ['']);
    }
}
