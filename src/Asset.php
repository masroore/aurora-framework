<?php

namespace Aurora;

use Aurora\Asset\AssetContainer;

class Asset
{
    /**
     * All of the instantiated asset containers.
     *
     * @var array
     */
    public static $containers = [];

    /**
     * Magic Method for calling methods on the default container.
     *
     * <code>
     *        // Call the "styles" method on the default container
     *        echo Asset::styles();
     *
     *        // Call the "add" method on the default container
     *        Asset::add('jquery', 'js/jquery.js');
     * </code>
     */
    public static function __callStatic($method, array $parameters)
    {
        return \call_user_func_array([static::container(), $method], $parameters);
    }

    /**
     * Get an asset container instance.
     *
     * <code>
     *        // Get the default asset container
     *        $container = Asset::container();
     *
     *        // Get a named asset container
     *        $container = Asset::container('footer');
     * </code>
     *
     * @param string $container
     *
     * @return AssetContainer
     */
    public static function container($container = 'default')
    {
        if (!isset(static::$containers[$container])) {
            static::$containers[$container] = new AssetContainer($container);
        }

        return static::$containers[$container];
    }
}
