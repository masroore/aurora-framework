<?php

namespace Aurora\Routing;

use Aurora\Bundle;
use Aurora\Str;

class Router
{
    /**
     * The route names that have been matched.
     */
    public static array $names = [];

    /**
     * The actions that have been reverse routed.
     */
    public static array $uses = [];

    /**
     * All of the routes that have been registered.
     */
    public static array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];

    /**
     * All of the "fallback" routes that have been registered.
     */
    public static array $fallback = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];

    /**
     * The current attributes being shared by routes.
     */
    public static array $group = [];

    /**
     * The "handles" clause for the bundle currently being routed.
     */
    public static ?string $bundle = null;

    /**
     * The number of URI segments allowed as method arguments.
     */
    public static int $segments = 5;

    /**
     * The wildcard patterns supported by the router.
     */
    public static array $patterns = [
        '(:num)' => '([0-9]+)',
        '(:any)' => '([a-zA-Z0-9\.\-_%=]+)',
        '(:segment)' => '([^/]+)',
        '(:all)' => '(.*)',
    ];

    /**
     * The optional wildcard patterns supported by the router.
     */
    public static array $optional = [
        '/(:num?)' => '(?:/([0-9]+)',
        '/(:any?)' => '(?:/([a-zA-Z0-9\.\-_%=]+)',
        '/(:segment?)' => '(?:/([^/]+)',
        '/(:all?)' => '(?:/(.*)',
    ];

    /**
     * An array of HTTP request methods.
     */
    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * Register a HTTPS route with the router.
     */
    public static function secure(string $method, array|string $route, $action): void
    {
        $action = static::action($action);

        $action['https'] = true;

        static::register($method, $route, $action);
    }

    /**
     * Convert a route action to a valid action array.
     *
     * @return array
     */
    protected static function action($action)
    {
        // If the action is a string, it is a pointer to a controller, so we
        // need to add it to the action array as a "uses" clause, which will
        // indicate to the route to call the controller.
        if (\is_string($action)) {
            $action = ['uses' => $action];
        }
        // If the action is a Closure, we will manually put it in an array
        // to work around a bug in PHP 5.3.2 which causes Closures cast
        // as arrays to become null. We'll remove this.
        elseif ($action instanceof \Closure) {
            $action = [$action];
        }

        return (array)$action;
    }

    /**
     * Register a route with the router.
     *
     * <code>
     *        // Register a route with the router
     *        Router::register('GET', '/', function() {return 'Home!';});
     *
     *        // Register a route that handles multiple URIs with the router
     *        Router::register(array('GET', '/', 'GET /home'), function() {return 'Home!';});
     * </code>
     */
    public static function register(string $method, array|string $route, $action): void
    {
        if (\is_string($route) && ctype_digit((string)$route)) {
            $route = "({$route})";
        }

        if (\is_string($route)) {
            $route = explode(', ', $route);
        }

        // If the developer is registering multiple request methods to handle
        // the URI, we'll spin through each method and register the route
        // for each of them along with each URI and action.
        if (\is_array($method)) {
            foreach ($method as $http) {
                static::register($http, $route, $action);
            }

            return;
        }

        $the_group = static::$group;
        if (isset($the_group['as'])) {
            unset($the_group['as']);
        }

        foreach ((array)$route as $uri) {
            // If the URI begins with a splat, we'll call the universal method, which
            // will register a route for each of the request methods supported by
            // the router. This is just a notational short-cut.
            if ('*' === $method) {
                foreach (static::$methods as $m) {
                    static::register($m, $route, $action);
                }

                continue;
            }

            if (null !== static::$bundle) {
                $uri = str_replace('(:bundle)', static::$bundle, $uri);
            }
            $uri = ltrim($uri, '/');

            if ('' === $uri) {
                $uri = '/';
            }

            // If the URI begins with a wildcard, we want to add this route to the
            // array of "fallback" routes. Fallback routes are always processed
            // last when parsing routes since they are very generic and could
            // overload bundle routes that are registered.
            if ('(' === $uri[0]) {
                $routes = &static::$fallback;
            } else {
                $routes = &static::$routes;
            }

            $prefix = '';
            if (\is_array($action) && isset($action['prefix'])) {
                $prefix = $action['prefix'];
            }

            $prefix = static::getPrefix($prefix);
            if (!blank($prefix)) {
                $uri = rtrim($prefix, '/') . '/' . ltrim($uri, '/');
            }

            if (\is_array($action) && isset(static::$group['as'], $action['as']) && !blank($action['as'])) {
                $action['as'] = trim(static::$group['as']) . trim($action['as']);
            }

            // If the action is an array, we can simply add it to the array of
            // routes keyed by the URI. Otherwise, we will need to call into
            // the action method to get a valid action array.
            if (\is_array($action)) {
                $routes[$method][$uri] = $action;
            } else {
                $routes[$method][$uri] = static::action($action);
            }

            // If a group is being registered, we'll merge all of the group
            // options into the action, giving preference to the action
            // for options that are specified in both.
            if (null !== static::$group) {
                $routes[$method][$uri] += $the_group;
            }

            // If the HTTPS option is not set on the action, we'll use the
            // value given to the method. The secure method passes in the
            // HTTPS value in as a parameter short-cut.
            if (!isset($routes[$method][$uri]['https'])) {
                $routes[$method][$uri]['https'] = false;
            }
        }
    }

    public static function getPrefix(string $prefix): string
    {
        $prefixes = [];
        if (isset(static::$group['prefix'])) {
            $prefixes[] = trim(static::$group['prefix'], '/');
        }
        $prefixes[] = trim($prefix, '/');

        return implode('/', $prefixes);
    }

    /**
     * Register many request URIs to a single action.
     *
     * <code>
     *        // Register a group of URIs for an action
     *        Router::share(array(array('GET', '/'), array('POST', '/')), 'home@index');
     * </code>
     *
     * @param array $routes
     */
    public static function share($routes, $action): void
    {
        foreach ($routes as $route) {
            static::register($route[0], $route[1], $action);
        }
    }

    /**
     * Register a group of routes that share attributes.
     */
    public static function group(array $attributes, \Closure $callback): void
    {
        // Route groups allow the developer to specify attributes for a group
        // of routes. To register them, we'll set a static property on the
        // router so that the register method will see them.
        static::$group = $attributes;

        $callback();

        // Once the routes have been registered, we want to set the group to
        // null so the attributes will not be given to any of the routes
        // that are added after the group is declared.
        static::$group = [];
    }

    /**
     * Register a secure controller with the router.
     *
     * @param array|string $controllers
     * @param array|string $defaults
     */
    public static function secure_controller($controllers, $defaults = 'index'): void
    {
        static::controller($controllers, $defaults, true);
    }

    /**
     * Register a controller with the router.
     *
     * @param array|string $controllers
     * @param array|string $defaults
     * @param bool         $https
     */
    public static function controller($controllers, $defaults = 'index', $https = null): void
    {
        foreach ((array)$controllers as $identifier) {
            [$bundle, $controller] = Bundle::parse($identifier);

            // First we need to replace the dots with slashes in the controller name
            // so that it is in directory format. The dots allow the developer to use
            // a cleaner syntax when specifying the controller. We will also grab the
            // root URI for the controller's bundle.
            $controller = str_replace('.', '/', $controller);

            $root = Bundle::option($bundle, 'handles');

            // If the controller is a "home" controller, we'll need to also build an
            // index method route for the controller. We'll remove "home" from the
            // route root and setup a route to point to the index method.
            if (Str::endsWith($controller, 'home')) {
                static::root($identifier, $controller, $root);
            }

            // The number of method arguments allowed for a controller is set by a
            // "segments" constant on this class which allows for the developer to
            // increase or decrease the limit on method arguments.
            $wildcards = static::repeat('(:any?)', static::$segments);

            // Once we have the path and root URI we can build a simple route for
            // the controller that should handle a conventional controller route
            // setup of controller/method/segment/segment, etc.
            $pattern = trim("{$root}/{$controller}/{$wildcards}", '/');

            // Finally we can build the "uses" clause and the attributes for the
            // controller route and register it with the router with a wildcard
            // method so it is available on every request method.
            $uses = "{$identifier}@(:1)";

            $attributes = compact('uses', 'defaults', 'https');

            static::register('*', $pattern, $attributes);
        }
    }

    /**
     * Register a route for the root of a controller.
     */
    protected static function root(string $identifier, string $controller, ?string $root): void
    {
        // First we need to strip "home" off of the controller name to create the
        // URI needed to match the controller's folder, which should match the
        // root URI we want to point to the index method.
        if ('home' !== $controller) {
            $home = \dirname($controller);
        } else {
            $home = '';
        }

        // After we trim the "home" off of the controller name we'll build the
        // pattern needed to map to the controller and then register a route
        // to point the pattern to the controller's index method.
        $pattern = trim($root . '/' . $home, '/') ?: '/';

        $attributes = ['uses' => "{$identifier}@index"];

        static::register('*', $pattern, $attributes);
    }

    /**
     * Get a string repeating a URI pattern any number of times.
     *
     * @param string $pattern
     * @param int    $times
     *
     * @return string
     */
    protected static function repeat($pattern, $times)
    {
        return implode('/', array_fill(0, $times, $pattern));
    }

    /**
     * Find a route by the route's assigned name.
     */
    public static function find(string $name): array
    {
        if (isset(static::$names[$name])) {
            return static::$names[$name];
        }

        // If no route names have been found at all, we will assume no reverse
        // routing has been done, and we will load the routes file for all of
        // the bundles that are installed for the application.
        if (0 === \count(static::$names)) {
            foreach (Bundle::names() as $bundle) {
                Bundle::routes($bundle);
            }
        }

        // To find a named route, we will iterate through every route defined
        // for the application. We will cache the routes by name so we can
        // load them very quickly the next time.
        foreach (static::routes() as $method => $routes) {
            foreach ($routes as $key => $value) {
                if (isset($value['as']) && $value['as'] === $name) {
                    return static::$names[$name] = [$key => $value];
                }
            }
        }

        return [];
    }

    /**
     * Get all of the registered routes, with fallbacks at the end.
     */
    public static function routes(): array
    {
        $routes = static::$routes;

        foreach (static::$methods as $method) {
            // It's possible that the routes array may not contain any routes for the
            // method, so we'll seed each request method with an empty array if it
            // doesn't already contain any routes.
            if (!isset($routes[$method])) {
                $routes[$method] = [];
            }

            $fallback = array_get(static::$fallback, $method, []);

            // When building the array of routes, we'll merge in all of the fallback
            // routes for each request method individually. This allows us to avoid
            // collisions when merging the arrays together.
            $routes[$method] = array_merge($routes[$method], $fallback);
        }

        return $routes;
    }

    /**
     * Find the route that uses the given action.
     */
    public static function uses(string $action): array
    {
        // If the action has already been reverse routed before, we'll just
        // grab the previously found route to save time. They are cached
        // in a static array on the class.
        if (isset(static::$uses[$action])) {
            return static::$uses[$action];
        }

        Bundle::routes(Bundle::name($action));

        // To find the route, we'll simply spin through the routes looking
        // for a route with a "uses" key matching the action, and if we
        // find one, we cache and return it.
        foreach (static::routes() as $method => $routes) {
            foreach ($routes as $key => $value) {
                if (isset($value['uses']) && $value['uses'] === $action) {
                    return static::$uses[$action] = [$key => $value];
                }
            }
        }
    }

    /**
     * Search the routes for the route matching a method and URI.
     */
    public static function route(string $method, string $uri): ?Route
    {
        Bundle::start($bundle = Bundle::handles($uri));

        $routes = (array)static::method($method);

        // Of course literal route matches are the quickest to find, so we will
        // check for those first. If the destination key exists in the routes
        // array we can just return that route now.
        if (\array_key_exists($uri, $routes)) {
            $action = $routes[$uri];

            return new Route($method, $uri, $action);
        }

        // If we can't find a literal match we'll iterate through all of the
        // registered routes to find a matching route based on the route's
        // regular expressions and wildcards.
        if (null !== ($route = static::match($method, $uri))) {
            return $route;
        }

        return null;
    }

    /**
     * Grab all of the routes for a given request method.
     */
    public static function method(string $method): array
    {
        $routes = array_get(static::$routes, $method, []);

        return array_merge($routes, array_get(static::$fallback, $method, []));
    }

    /**
     * Iterate through every route to find a matching route.
     */
    protected static function match(string $method, string $uri): ?Route
    {
        foreach (static::method($method) as $route => $action) {
            // We only need to check routes with regular expression since all others
            // would have been able to be matched by the search for literal matches
            // we just did before we started searching.
            if (str_contains($route, '(')) {
                $pattern = '#^' . static::wildcards($route) . '$#u';

                // If we get a match we'll return the route and slice off the first
                // parameter match, as preg_match sets the first array item to the
                // full-text match of the pattern.
                if (preg_match($pattern, $uri, $parameters)) {
                    return new Route($method, $route, $action, \array_slice($parameters, 1));
                }
            }
        }

        return null;
    }

    /**
     * Translate route URI wildcards into regular expressions.
     */
    protected static function wildcards(string $key): string
    {
        [$search, $replace] = array_divide(static::$optional);

        // For optional parameters, first translate the wildcards to their
        // regex equivalent, sans the ")?" ending. We'll add the endings
        // back on when we know the replacement count.
        $key = str_replace($search, $replace, $key, $count);

        if ($count > 0) {
            $key .= str_repeat(')?', $count);
        }

        return strtr($key, static::$patterns);
    }

    /**
     * Get all of the wildcard patterns.
     */
    public static function patterns(): array
    {
        return array_merge(static::$patterns, static::$optional);
    }
}
