<?php

namespace Aurora;

\defined('DS') || exit('No direct script access.');

use Aurora\Routing\Router;

class Bundle
{
    /**
     * All of the application's bundles.
     */
    public static array $bundles = [];

    /**
     * A cache of the parsed bundle elements.
     */
    public static array $elements = [];

    /**
     * All of the bundles that have been started.
     */
    public static array $started = [];

    /**
     * All of the bundles that have their routes files loaded.
     */
    public static array $routed = [];

    /**
     * Register the bundle for the application.
     */
    public static function register(string $bundle, array|string $config): void
    {
        $defaults = ['handles' => null, 'auto' => false];

        // If the given configuration is actually a string, we will assume it is a
        // location and set the bundle name to match it. This is common for most
        // bundles that simply live in the root bundle directory.
        if (\is_string($config)) {
            $bundle = $config;

            $config = ['location' => $bundle];
        }

        // If no location is set, we will set the location to match the name of
        // the bundle. This is for bundles that are installed on the root of
        // the bundle directory so a location was not set.
        if (!isset($config['location'])) {
            $config['location'] = $bundle;
        }

        static::$bundles[$bundle] = array_merge($defaults, $config);

        // It is possible for the developer to specify autoloader mappings
        // directly on the bundle registration. This provides a convenient
        // way to register mappings without a bootstrap.
        if (isset($config['autoloads'])) {
            static::autoloads($bundle, $config);
        }
    }

    /**
     * Register the autoloading configuration for a bundle.
     */
    protected static function autoloads(string $bundle, array $config): void
    {
        $path = rtrim(self::path($bundle), DS);

        foreach ($config['autoloads'] as $type => $mappings) {
            // When registering each type of mapping we'll replace the (:bundle)
            // place-holder with the path to the bundle's root directory, so
            // the developer may dryly register the mappings.
            $mappings = array_map(static fn ($mapping) => str_replace('(:bundle)', $path, $mapping), $mappings);

            // Once the mappings are formatted, we will call the Autoloader
            // function matching the mapping type and pass in the array of
            // mappings, so they can be registered and used.
            Autoloader::$type($mappings);
        }
    }

    /**
     * Return the root bundle path for a given bundle.
     *
     * <code>
     *        // Returns the bundle path for the "admin" bundle
     *        $path = Bundle::path('admin');
     *
     *        // Returns the APP_PATH constant as the default bundle
     *        $path = Bundle::path('application');
     * </code>
     */
    public static function path(?string $bundle): ?string
    {
        if (null === $bundle || DEFAULT_BUNDLE === $bundle) {
            return APP_PATH;
        }
        if ($location = array_get(static::$bundles, $bundle . '.location')) {
            // If the bundle location starts with "path: ", we will assume that a raw
            // path has been specified and will simply return it. Otherwise, we'll
            // prepend the bundle directory path onto the location and return.
            if (Str::startsWith($location, 'path: ')) {
                return Str::finish(mb_substr($location, 6), DS);
            }

            return Str::finish(path('bundle') . $location, DS);
        }

        return null;
    }

    /**
     * Load a bundle by running its start-up script.
     *
     * If the bundle has already been started, no action will be taken.
     */
    public static function start(string $bundle): void
    {
        if (static::started($bundle)) {
            return;
        }

        if (!static::exists($bundle)) {
            throw new \Exception("Bundle [$bundle] has not been installed.");
        }

        // Each bundle may have a start script which is responsible for preparing
        // the bundle for use by the application. The start script may register
        // any classes the bundle uses with the auto-loader class, etc.
        if (null !== ($starter = static::option($bundle, 'starter'))) {
            $starter();
        } elseif (file_exists($path = static::path($bundle) . 'start' . EXT)) {
            require $path;
        }

        // Each bundle may also have a "routes" file which is responsible for
        // registering the bundle's routes. This is kept separate from the
        // start script for reverse routing efficiency purposes.
        static::routes($bundle);

        Event::fire("aurora.started: {$bundle}");

        static::$started[] = mb_strtolower($bundle);
    }

    /**
     * Determine if a given bundle has been started for the request.
     */
    public static function started(string $bundle): bool
    {
        return \in_array(mb_strtolower($bundle), static::$started, true);
    }

    /**
     * Determine if a bundle exists within the bundles directory.
     */
    public static function exists(string $bundle): bool
    {
        return DEFAULT_BUNDLE === $bundle || \in_array(mb_strtolower($bundle), static::names(), true);
    }

    /**
     * Get all the installed bundle names.
     */
    public static function names(): array
    {
        return array_keys(static::$bundles);
    }

    /**
     * Get an option for a given bundle.
     */
    public static function option(string $bundle, string $option, mixed $default = null)
    {
        $buns = static::get($bundle);

        if (null === $buns) {
            return value($default);
        }

        return array_get($buns, $option, $default);
    }

    /**
     * Get the information for a given bundle.
     */
    public static function get(string $bundle)
    {
        return array_get(static::$bundles, $bundle);
    }

    /**
     * Load the "routes" file for a given bundle.
     */
    public static function routes(string $bundle): void
    {
        if (static::routed($bundle)) {
            return;
        }

        $path = static::path($bundle) . 'routes' . EXT;

        // By setting the bundle property on the router, the router knows what
        // value to replace the (:bundle) place-holder with when the bundle
        // routes are added, keeping the routes flexible.
        Router::$bundle = static::option($bundle, 'handles');

        if (!static::routed($bundle) && file_exists($path)) {
            static::$routed[] = $bundle;

            require $path;
        }
    }

    /**
     * Determine if a given bundle has its routes file loaded.
     */
    public static function routed(string $bundle): bool
    {
        return \in_array(mb_strtolower($bundle), static::$routed, true);
    }

    /**
     * Disable a bundle for the current request.
     */
    public static function disable(string $bundle): void
    {
        unset(static::$bundles[$bundle]);
    }

    /**
     * Determine which bundle handles the given URI.
     *
     * The default bundle is returned if no other bundle is assigned.
     */
    public static function handles(string $uri): string
    {
        $uri = rtrim($uri, '/') . '/';

        foreach (static::$bundles as $key => $value) {
            if (isset($value['handles']) && Str::startsWith($uri, $value['handles'] . '/') || '/' === $value['handles']) {
                return $key;
            }
        }

        return DEFAULT_BUNDLE;
    }

    /**
     * Get the identifier prefix for the bundle.
     */
    public static function prefix(string $bundle): string
    {
        return (DEFAULT_BUNDLE !== $bundle) ? "{$bundle}::" : '';
    }

    /**
     * Get the class prefix for a given bundle.
     */
    public static function classPrefix(string $bundle): string
    {
        return (DEFAULT_BUNDLE !== $bundle) ? Str::classify($bundle) . '_' : '';
    }

    /**
     * Return the root asset path for the given bundle.
     */
    public static function assets(?string $bundle): string
    {
        if (null === $bundle) {
            return static::assets(DEFAULT_BUNDLE);
        }

        return (DEFAULT_BUNDLE !== $bundle) ? "/bundles/{$bundle}/" : '/';
    }

    /**
     * Get the bundle name from a given identifier.
     *
     * <code>
     *        // Returns "admin" as the bundle name for the identifier
     *        $bundle = Bundle::name('admin::home.index');
     * </code>
     */
    public static function name(string $identifier): string
    {
        [$bundle, $_] = static::parse($identifier);

        return $bundle;
    }

    /**
     * Parse an element identifier and return the bundle name and element.
     *
     * <code>
     *        // Returns array(null, 'admin.user')
     *        $element = Bundle::parse('admin.user');
     *
     *        // Parses "admin::user" and returns array('admin', 'user')
     *        $element = Bundle::parse('admin::user');
     * </code>
     */
    public static function parse(string $identifier): array
    {
        // The parsed elements are cached so we don't have to reparse them on each
        // subsequent request for the parsed element. So if we've already parsed
        // the given element, we'll just return the cached copy as the value.
        if (isset(static::$elements[$identifier])) {
            return static::$elements[$identifier];
        }

        if (false !== mb_strpos($identifier, '::')) {
            $element = explode('::', mb_strtolower($identifier));
        }
        // If no bundle is in the identifier, we will insert the default bundle
        // since classes like Config and Lang organize their items by bundle.
        // The application folder essentially behaves as a default bundle.
        else {
            $element = [DEFAULT_BUNDLE, mb_strtolower($identifier)];
        }

        return static::$elements[$identifier] = $element;
    }

    /**
     * Get the element name from a given identifier.
     *
     * <code>
     *        // Returns "home.index" as the element name for the identifier
     *        $bundle = Bundle::bundle('admin::home.index');
     * </code>
     */
    public static function element(string $identifier): string
    {
        [$_, $element] = static::parse($identifier);

        return $element;
    }

    /**
     * Reconstruct an identifier from a given bundle and element.
     *
     * <code>
     *        // Returns "admin::home.index"
     *        $identifier = Bundle::identifier('admin', 'home.index');
     *
     *        // Returns "home.index"
     *        $identifier = Bundle::identifier('application', 'home.index');
     * </code>
     */
    public static function identifier(?string $bundle, string $element): string
    {
        return (null === $bundle || DEFAULT_BUNDLE === $bundle) ? $element : $bundle . '::' . $element;
    }

    /**
     * Return the bundle name if it exists, else return the default bundle.
     */
    public static function resolve(string $bundle): string
    {
        return (static::exists($bundle)) ? $bundle : DEFAULT_BUNDLE;
    }

    /**
     * Get all of the installed bundles for the application.
     */
    public static function all(): array
    {
        return static::$bundles;
    }

    /**
     * Expand given bundle path of form "[bundle::]path/...".
     */
    public static function expand(string $path): string
    {
        [$bundle, $element] = static::parse($path);

        return static::path($bundle) . $element;
    }
}
