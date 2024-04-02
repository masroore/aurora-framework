<?php

namespace Aurora;

use Aurora\Routing\Router;

class Url
{
    /**
     * The cached base URL.
     *
     * @var string
     */
    public static $base;

    /**
     * Get the full URI including the query string.
     *
     * @return string
     */
    public static function full()
    {
        return static::to(Uri::full());
    }

    /**
     * Generate an application URL.
     *
     * <code>
     *        // Create a URL to a location within the application
     *        $url = URL::to('user/profile');
     *
     *        // Create a HTTPS URL to a location within the application
     *        $url = URL::to('user/profile', true);
     * </code>
     *
     * @param string $url
     * @param bool   $https
     * @param bool   $asset
     * @param bool   $locale
     *
     * @return string
     */
    public static function to($url = '', $https = null, $asset = false, $locale = true)
    {
        // If the given URL is already valid or begins with a hash, we'll just return
        // the URL unchanged since it is already well formed. Otherwise we will add
        // the base URL of the application and return the full URL.
        if (static::valid($url) || Str::startsWith($url, '#')) {
            return $url;
        }

        // Unless $https is specified (true or false), we maintain the current request
        // security for any new links generated.  So https for all secure links.
        if (null === $https) {
            $https = Request::secure();
        }

        $root = static::base();

        if (!$asset) {
            $root .= '/' . Config::get('app.index');
        }

        $languages = Config::get('app.languages');

        if (!$asset && $locale && \count($languages) > 0 && \in_array($default = Config::get('app.language'), $languages, true)) {
            $root = rtrim($root, '/') . '/' . $default;
        }

        // Since SSL is not often used while developing the application, we allow the
        // developer to disable SSL on all framework generated links to make it more
        // convenient to work with the site while developing locally.
        if ($https && Config::get('app.ssl')) {
            $root = preg_replace('~http://~', 'https://', $root, 1);
        } else {
            $root = preg_replace('~https://~', 'http://', $root, 1);
        }

        return rtrim($root, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Determine if the given URL is valid.
     *
     * @param string $url
     *
     * @return bool
     */
    public static function valid($url)
    {
        if (Str::startsWith($url, '//')) {
            return true;
        }

        return false !== filter_var($url, \FILTER_VALIDATE_URL);
    }

    /**
     * Get the base URL of the application.
     *
     * @return string
     */
    public static function base()
    {
        if (isset(static::$base)) {
            return static::$base;
        }

        $base = 'http://localhost';

        // If the application's URL configuration is set, we will just use that
        // instead of trying to guess the URL from the $_SERVER array's host
        // and script variables as this is a more reliable method.
        if ('' !== ($url = Config::get('app.url'))) {
            $base = $url;
        } else {
            $base = Request::foundation()->getRootUrl();
        }

        return static::$base = $base;
    }

    /**
     * Get the full URL for the current request.
     *
     * @return string
     */
    public static function current()
    {
        return static::to(Uri::current(), null, false, false);
    }

    /**
     * Generate an application URL with HTTPS.
     *
     * @param string $url
     *
     * @return string
     */
    public static function secure($url = '')
    {
        return static::to($url, true);
    }

    /**
     * Generate a URL to a controller action.
     *
     * <code>
     *        // Generate a URL to the "index" method of the "user" controller
     *        $url = URL::to_action('user@index');
     *
     *        // Generate a URL to http://example.com/user/profile/taylor
     *        $url = URL::to_action('user@profile', array('taylor'));
     * </code>
     *
     * @param string $action
     * @param array  $parameters
     *
     * @return string
     */
    public static function action($action, $parameters = [])
    {
        // This allows us to use true reverse routing to controllers, since
        // URIs may be setup to handle the action that do not follow the
        // typical Aurora controller URI conventions.
        $route = Router::uses($action);

        if (null !== $route) {
            return static::explicit($route, $action, $parameters);
        }

        // If no route was found that handled the given action, we'll just
        // generate the URL using the typical controller routing setup
        // for URIs and turn SSL to false by default.
        return static::convention($action, $parameters);
    }

    /**
     * Substitute the parameters in a given URI.
     *
     * @param string $uri
     * @param array  $parameters
     *
     * @return string
     */
    public static function transpose($uri, $parameters)
    {
        // Spin through each route parameter and replace the route wildcard segment
        // with the corresponding parameter passed to the method. Afterwards, we'll
        // replace all of the remaining optional URI segments.
        foreach ((array)$parameters as $parameter) {
            if (null !== $parameter) {
                $uri = preg_replace('/\(.+?\)/', $parameter, $uri, 1);
            }
        }

        // If there are any remaining optional place-holders, we'll just replace
        // them with empty strings since not every optional parameter has to be
        // in the array of parameters that were passed to us.
        $uri = preg_replace('/\(.+?\)/', '', $uri ?? '');

        return trim($uri, '/');
    }

    /**
     * Generate an application URL to an asset.
     *
     * @param string $url
     * @param bool   $https
     *
     * @return string
     */
    public static function asset($url, $https = null)
    {
        if (static::valid($url) || static::valid('http:' . $url)) {
            return $url;
        }

        // If a base asset URL is defined in the configuration, use that and don't
        // try and change the HTTP protocol. This allows the delivery of assets
        // through a different server or third-party content delivery network.
        if ($root = Config::get('app.asset_url', false)) {
            return rtrim($root, '/') . '/' . ltrim($url, '/');
        }

        $url = static::to($url, $https, true);

        // Since assets are not served by Aurora, we do not need to come through
        // the front controller. So, we'll remove the application index specified
        // in the application config from the generated URL.
        if ('' !== ($index = Config::get('app.index'))) {
            $url = str_replace($index . '/', '', $url);
        }

        return $url;
    }

    /**
     * Get the URL to switch language, keeping the current page or not.
     *
     * @param string $language The new language
     * @param bool   $reset    Whether navigation should be reset
     *
     * @return string An URL
     */
    public static function to_language($language, $reset = false)
    {
        // Get the url to use as base
        $url = $reset ? self::home() : self::to(Uri::current());

        // Validate the language
        if (!\in_array($language, Config::get('app.languages'), true)) {
            return $url;
        }

        // Get the language we're switching from and the one we're going to
        $from = '/' . Config::get('app.language') . '/';
        $to = '/' . $language . '/';

        return str_replace($from, $to, $url);
    }

    /**
     * Get the URL for the application root.
     *
     * @param bool $https
     *
     * @return string
     */
    public static function home($https = null)
    {
        $route = Router::find('home');

        // If a route named "home" exists, we'll route to that instead of using
        // the single slash root URI. This allows the HTTPS attribute to be
        // respected instead of being hard-coded in the redirect.
        if (null !== $route) {
            return static::route('home');
        }

        return static::to('/', $https);
    }

    /**
     * Generate a URL from a route name.
     *
     * <code>
     *        // Create a URL to the "profile" named route
     *        $url = URL::route('profile');
     *
     *        // Create a URL to the "profile" named route with wildcard parameters
     *        $url = URL::route('profile', array($username));
     * </code>
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return string
     */
    public static function route($name, $parameters = [])
    {
        if (null === $route = Router::find($name)) {
            throw new \Exception("Error creating URL for undefined route [$name].");
        }

        // To determine whether the URL should be HTTPS or not, we look for the "https"
        // value on the route action array. The route has control over whether the URL
        // should be generated with an HTTPS protocol string or just HTTP.
        $https = array_get(current($route), 'https', null);

        $uri = trim(static::transpose(key($route), $parameters), '/');

        return static::to($uri, $https);
    }

    /**
     * Generate an action URL from a route definition.
     *
     * @param array  $route
     * @param string $action
     * @param array  $parameters
     *
     * @return string
     */
    protected static function explicit($route, $action, $parameters)
    {
        $https = array_get(current($route), 'https', null);

        return static::to(static::transpose(key($route), $parameters), $https);
    }

    /**
     * Generate an action URI by convention.
     *
     * @param string $action
     * @param array  $parameters
     *
     * @return string
     */
    protected static function convention($action, $parameters)
    {
        [$bundle, $action] = Bundle::parse($action);

        $bundle = Bundle::get($bundle);

        // If a bundle exists for the action, we will attempt to use its "handles"
        // clause as the root of the generated URL, as the bundle can only handle
        // URIs that begin with that string and no others.
        $root = $bundle['handles'] ?: '';

        $parameters = implode('/', $parameters);

        // We'll replace both dots and @ signs in the URI since both are used
        // to specify the controller and action, and by convention should be
        // translated into URI slashes for the URL.
        $uri = $root . '/' . str_replace(['.', '@'], '/', $action);

        $uri = static::to(Str::finish($uri, '/') . $parameters);

        return trim($uri, '/');
    }
}
