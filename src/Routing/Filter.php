<?php

namespace Aurora\Routing;

use Aurora\Bundle;
use Aurora\Str;
use Closure;

class Filter
{
    /**
     * The route filters for the application.
     *
     * @var array
     */
    public static $filters = [];

    /**
     * The route filters that are based on a pattern.
     *
     * @var array
     */
    public static $patterns = [];

    /**
     * All of the registered filter aliases.
     *
     * @var array
     */
    public static $aliases = [];

    /**
     * Register a filter for the application.
     *
     * <code>
     *        // Register a closure as a filter
     *        Filter::register('before', function() {});
     *
     *        // Register a class callback as a filter
     *        Filter::register('before', array('Class', 'method'));
     * </code>
     */
    public static function register(string $name, $callback): void
    {
        if (isset(static::$aliases[$name])) {
            $name = static::$aliases[$name];
        }

        // If the filter starts with "pattern: ", the filter is being setup to match on
        // all requests that match a given pattern. This is nice for defining filters
        // that handle all URIs beginning with "admin" for example.
        if (Str::startsWith($name, 'pattern: ')) {
            foreach (explode(', ', substr($name, 9)) as $pattern) {
                static::$patterns[$pattern] = $callback;
            }
        } else {
            static::$filters[$name] = $callback;
        }
    }

    /**
     * Alias a filter so it can be used by another name.
     *
     * This is convenient for shortening filters that are registered by bundles.
     *
     * @param string $filter
     * @param string $alias
     */
    public static function alias($filter, $alias): void
    {
        static::$aliases[$alias] = $filter;
    }

    /**
     * Parse a filter definition into an array of filters.
     *
     * @param array|string $filters
     *
     * @return array
     */
    public static function parse($filters)
    {
        return (\is_string($filters)) ? explode('|', $filters) : (array)$filters;
    }

    /**
     * Call a filter or set of filters.
     *
     * @param array $collections
     * @param array $pass
     * @param bool  $override
     */
    public static function run($collections, $pass = [], $override = false)
    {
        foreach ($collections as $collection) {
            foreach ($collection->filters as $filter) {
                [$filter, $parameters] = $collection->get($filter);

                // We will also go ahead and start the bundle for the developer. This allows
                // the developer to specify bundle filters on routes without starting the
                // bundle manually, and performance is improved by lazy-loading.
                Bundle::start(Bundle::name($filter));

                if (!isset(static::$filters[$filter])) {
                    continue;
                }

                $callback = static::$filters[$filter];

                // Parameters may be passed into filters by specifying the list of parameters
                // as an array, or by registering a Closure which will return the array of
                // parameters. If parameters are present, we will merge them with the
                // parameters that were given to the method.
                $response = \call_user_func_array($callback, array_merge($pass, $parameters));

                // "Before" filters may override the request cycle. For example, an auth
                // filter may redirect a user to a login view if they are not logged in.
                // Because of this, we will return the first filter response if
                // overriding is enabled for the filter collections
                if (null !== $response && $override) {
                    return $response;
                }
            }
        }
    }
}
