<?php

namespace Aurora;

\defined('DS') || exit('No direct script access.');

class Config
{
    /**
     * The configuration loader event name.
     *
     * @var string
     */
    public const loader = 'aurora.config.loader';

    /**
     * All of the loaded configuration items.
     *
     * The configuration arrays are keyed by their owning bundle and file.
     */
    public static array $items = [];

    /**
     * A cache of the parsed configuration items.
     */
    public static array $cache = [];

    /**
     * Determine if a configuration item or file exists.
     *
     * <code>
     *        // Determine if the "session" configuration file exists
     *        $exists = Config::has('session');
     *
     *        // Determine if the "timezone" option exists in the configuration
     *        $exists = Config::has('app.timezone');
     * </code>
     */
    public static function has(string $key): bool
    {
        return null !== static::get($key);
    }

    /**
     * Get a configuration item.
     *
     * If no item is requested, the entire configuration array will be returned.
     *
     * <code>
     *        // Get the "session" configuration array
     *        $session = Config::get('session');
     *
     *        // Get a configuration item from a bundle's configuration file
     *        $name = Config::get('admin::names.first');
     *
     *        // Get the "timezone" option from the "application" configuration file
     *        $timezone = Config::get('app.timezone');
     * </code>
     */
    public static function get(string $key, mixed $default = null)
    {
        [$bundle, $file, $item] = static::parse($key);

        if (!static::load($bundle, $file)) {
            return value($default);
        }

        $items = static::$items[$bundle][$file];

        // If a specific configuration item was not requested, the key will be null,
        // meaning we'll return the entire array of configuration items from the
        // requested configuration file. Otherwise we can return the item.
        return null === $item ? $items : array_get($items, $item, $default);
    }

    /**
     * Load all of the configuration items from a configuration file.
     */
    public static function load(string $bundle, string $file): bool
    {
        if (isset(static::$items[$bundle][$file])) {
            return true;
        }

        // We allow a "config.loader" event to be registered which is responsible for
        // returning an array representing the configuration for the bundle and file
        // requested. This allows many types of config "drivers".
        $config = Event::first(static::loader, \func_get_args());

        // If configuration items were actually found for the bundle and file, we
        // will add them to the configuration array and return true, otherwise
        // we will return false indicating the file was not found.
        if (\count((array)$config) > 0) {
            static::$items[$bundle][$file] = $config;
        }

        return isset(static::$items[$bundle][$file]);
    }

    /**
     * Set a configuration item's value.
     *
     * <code>
     *        // Set the "session" configuration array
     *        Config::set('session', $array);
     *
     *        // Set a configuration option that belongs by a bundle
     *        Config::set('admin::names.first', 'Taylor');
     *
     *        // Set the "timezone" option in the "application" configuration file
     *        Config::set('app.timezone', 'UTC');
     * </code>
     */
    public static function set(string $key, mixed $value): void
    {
        [$bundle, $file, $item] = static::parse($key);

        static::load($bundle, $file);

        // If the item is null, it means the developer wishes to set the entire
        // configuration array to a given value, so we will pass the entire
        // array for the bundle into the array_set method.
        if (null === $item) {
            array_set(static::$items[$bundle], $file, $value);
        } else {
            array_set(static::$items[$bundle][$file], $item, $value);
        }
    }

    /**
     * Load the configuration items from a configuration file.
     */
    public static function file(string $bundle, string $file): array
    {
        $config = [];

        // Configuration files cascade. Typically, the bundle configuration array is
        // loaded first, followed by the environment array, providing the convenient
        // cascading of configuration options across environments.
        foreach (static::paths($bundle) as $directory) {
            if ('' !== $directory && file_exists($path = $directory . $file . EXT)) {
                $config = array_merge($config, require $path);
            }
        }

        return $config;
    }

    /**
     * Parse a key and return its bundle, file, and key segments.
     *
     * Configuration items are named using the {bundle}::{file}.{item} convention.
     *
     * @return array
     */
    protected static function parse(string $key)
    {
        // First, we'll check the keyed cache of configuration items, as this will
        // be the fastest method of retrieving the configuration option. After an
        // item is parsed, it is always stored in the cache by its key.
        if (\array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $bundle = Bundle::name($key);

        $segments = explode('.', Bundle::element($key));

        // If there are not at least two segments in the array, it means that the
        // developer is requesting the entire configuration array to be returned.
        // If that is the case, we'll make the item field "null".
        if (\count($segments) >= 2) {
            $parsed = [$bundle, $segments[0], implode('.', \array_slice($segments, 1))];
        } else {
            $parsed = [$bundle, $segments[0], null];
        }

        return static::$cache[$key] = $parsed;
    }

    /**
     * Get the array of configuration paths that should be searched for a bundle.
     */
    protected static function paths(string $bundle): array
    {
        $paths[] = Bundle::path($bundle) . 'config/';

        // Configuration files can be made specific for a given environment. If an
        // environment has been set, we will merge the environment configuration
        // in last, so that it overrides all other options.
        if (null !== Request::env()) {
            $paths[] = $paths[\count($paths) - 1] . Request::env() . '/';
        }

        return $paths;
    }
}
