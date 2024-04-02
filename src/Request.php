<?php

namespace Aurora;

class Request
{
    /**
     * The request data key that is used to indicate a spoofed request method.
     *
     * @var string
     */
    public const spoofer = '_method';

    /**
     * All of the route instances handling the request.
     *
     * @var array
     */
    public static $route;

    /**
     * The Symfony HttpFoundation Request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    public static $foundation;

    /**
     * Pass any other methods to the Symfony request.
     *
     * @param string $method
     * @param array  $parameters
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::foundation(), $method], $parameters);
    }

    /**
     * Get the URI for the current request.
     *
     * @return string
     */
    public static function uri()
    {
        return Uri::current();
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public static function method()
    {
        $method = static::foundation()->getMethod();

        return ('HEAD' === $method) ? 'GET' : $method;
    }

    /**
     * Get the Symfony HttpFoundation Request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public static function foundation()
    {
        return static::$foundation;
    }

    /**
     * Get a header from the request.
     *
     * <code>
     *        // Get a header from the request
     *        $referer = Request::header('referer');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public static function header($key, $default = null)
    {
        return array_get(static::foundation()->headers->all(), $key, $default);
    }

    /**
     * Get all of the HTTP request headers.
     *
     * @return array
     */
    public static function headers()
    {
        return static::foundation()->headers->all();
    }

    /**
     * Get an item from the $_SERVER array.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return string
     */
    public static function server($key = null, $default = null)
    {
        return array_get(static::foundation()->server->all(), mb_strtoupper($key), $default);
    }

    /**
     * Determine if the request method is being spoofed by a hidden Form element.
     *
     * @return bool
     */
    public static function spoofed()
    {
        return null !== static::foundation()->get(self::spoofer);
    }

    /**
     * Get the requestor's IP address.
     *
     * @return string
     */
    public static function ip($default = '0.0.0.0')
    {
        $client_ip = static::foundation()->getClientIp();

        return null === $client_ip ? $default : $client_ip;
    }

    /**
     * Determine if the request accepts a given content type.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function accepts($type)
    {
        return \in_array($type, static::accept(), true);
    }

    /**
     * Get the list of acceptable content types for the request.
     *
     * @return array
     */
    public static function accept()
    {
        return static::foundation()->getAcceptableContentTypes();
    }

    /**
     * Get the languages accepted by the client's browser.
     *
     * @return array
     */
    public static function languages()
    {
        return static::foundation()->getLanguages();
    }

    /**
     * Determine if the current request is using HTTPS.
     *
     * @return bool
     */
    public static function secure()
    {
        return static::foundation()->isSecure() && Config::get('app.ssl');
    }

    /**
     * Determine if the request has been forged.
     *
     * The session CSRF token will be compared to the CSRF token in the request input.
     *
     * @return bool
     */
    public static function forged()
    {
        return Input::get(Session::CSRF_TOKEN) !== Session::token();
    }

    /**
     * Determine if the current request is an AJAX request.
     *
     * @return bool
     */
    public static function ajax()
    {
        return static::foundation()->isXmlHttpRequest();
    }

    /**
     * Get the HTTP referrer for the request.
     *
     * @return string
     */
    public static function referrer()
    {
        return static::foundation()->headers->get('referer');
    }

    /**
     * Get the timestamp of the time when the request was started.
     *
     * @return int
     */
    public static function time()
    {
        return (int)AURORA_START;
    }

    /**
     * Determine if the current request is via the command line.
     *
     * @return bool
     */
    public static function cli()
    {
        return \PHP_SAPI === 'cli';
        // return \defined('STDIN') || (\PHP_SAPI != 'cgi-fcgi' && 'cgi' == substr(\PHP_SAPI, 0, 3) && getenv('TERM'));
    }

    /**
     * Set the Aurora environment for the current request.
     *
     * @param string $env
     */
    public static function set_env($env): void
    {
        static::foundation()->server->set('AURORA_ENV', $env);
    }

    /**
     * Determine the current request environment.
     *
     * @param string $env
     *
     * @return bool
     */
    public static function is_env($env)
    {
        return static::env() === $env;
    }

    /**
     * Get the Aurora environment for the current request.
     *
     * @return string|null
     */
    public static function env()
    {
        return static::foundation()->server->get('AURORA_ENV');
    }

    /**
     * Detect the current environment from an environment configuration.
     *
     * @param string $uri
     *
     * @return string|null
     */
    public static function detect_env(array $environments, $uri)
    {
        foreach ($environments as $environment => $patterns) {
            // Essentially we just want to loop through each environment pattern
            // and determine if the current URI matches the pattern and if so
            // we will simply return the environment for that URI pattern.
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $uri) || $pattern === gethostname()) {
                    return $environment;
                }
            }
        }
    }

    /**
     * Get the main route handling the request.
     *
     * @return Route
     */
    public static function route()
    {
        return static::$route;
    }
}
