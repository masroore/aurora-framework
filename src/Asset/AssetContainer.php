<?php

namespace Aurora\Asset;

use Aurora\Bundle;
use Aurora\HTML;

class AssetContainer
{
    /**
     * The asset container name.
     *
     * @var string
     */
    public $name;

    /**
     * The bundle that the assets belong to.
     *
     * @var string
     */
    public $bundle = DEFAULT_BUNDLE;

    /**
     * All of the registered assets.
     *
     * @var array
     */
    public $assets = [];

    /**
     * Create a new asset container instance.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Add an asset to the container.
     *
     * The extension of the asset source will be used to determine the type of
     * asset being registered (CSS or JavaScript). When using a non-standard
     * extension, the style/script methods may be used to register assets.
     *
     * <code>
     *        // Add an asset to the container
     *        Asset::container()->add('jquery', 'js/jquery.js');
     *
     *        // Add an asset that has dependencies on other assets
     *        Asset::add('jquery', 'js/jquery.js', 'jquery-ui');
     *
     *        // Add an asset that should have attributes applied to its tags
     *        Asset::add('jquery', 'js/jquery.js', null, array('defer'));
     * </code>
     *
     * @param string $name
     * @param string $source
     *
     * @return AssetContainer
     */
    public function add($name, $source, array $dependencies = [], array $attributes = [])
    {
        $type = ('css' === pathinfo($source, \PATHINFO_EXTENSION)) ? 'style' : 'script';

        return $this->$type($name, $source, $dependencies, $attributes);
    }

    /**
     * Add a CSS file to the registered assets.
     *
     * @param string $name
     * @param string $source
     *
     * @return AssetContainer
     */
    public function style($name, $source, array $dependencies = [], array $attributes = [])
    {
        if (!\array_key_exists('media', $attributes)) {
            $attributes['media'] = 'all';
        }

        $this->register('style', $name, $source, $dependencies, $attributes);

        return $this;
    }

    /**
     * Add a JavaScript file to the registered assets.
     *
     * @param string $name
     * @param string $source
     *
     * @return AssetContainer
     */
    public function script($name, $source, array $dependencies = [], array $attributes = [])
    {
        $this->register('script', $name, $source, $dependencies, $attributes);

        return $this;
    }

    /**
     * Set the bundle that the container's assets belong to.
     *
     * @param string $bundle
     *
     * @return AssetContainer
     */
    public function bundle($bundle)
    {
        $this->bundle = $bundle;

        return $this;
    }

    /**
     * Get the links to all of the registered CSS assets.
     *
     * @return string
     */
    public function styles()
    {
        return $this->group('style');
    }

    /**
     * Returns the full-path for an asset.
     *
     * @param string $source
     *
     * @return string
     */
    public function path($source)
    {
        return Bundle::assets($this->bundle) . $source;
    }

    /**
     * Get the links to all of the registered JavaScript assets.
     *
     * @return string
     */
    public function scripts()
    {
        return $this->group('script');
    }

    /**
     * Add an asset to the array of registered assets.
     *
     * @param string $type
     * @param string $name
     * @param string $source
     */
    protected function register($type, $name, $source, array $dependencies, array $attributes): void
    {
        $this->assets[$type][$name] = compact('source', 'dependencies', 'attributes');
    }

    /**
     * Get all of the registered assets for a given type / group.
     *
     * @param string $group
     *
     * @return string
     */
    protected function group($group)
    {
        if (!isset($this->assets[$group]) || 0 === \count($this->assets[$group])) {
            return '';
        }

        $assets = '';

        foreach ($this->arrange($this->assets[$group]) as $name => $data) {
            $assets .= $this->asset($group, $name);
        }

        return $assets;
    }

    /**
     * Sort and retrieve assets based on their dependencies.
     *
     * @return array
     */
    protected function arrange(array $assets)
    {
        [$original, $sorted] = [$assets, []];

        while (\count($assets) > 0) {
            foreach ($assets as $asset => $value) {
                $this->evaluate_asset($asset, $value, $original, $sorted, $assets);
            }
        }

        return $sorted;
    }

    /**
     * Evaluate an asset and its dependencies.
     *
     * @param string $asset
     * @param string $value
     */
    protected function evaluate_asset($asset, $value, array $original, array &$sorted, array &$assets): void
    {
        // If the asset has no more dependencies, we can add it to the sorted list
        // and remove it from the array of assets. Otherwise, we will not verify
        // the asset's dependencies and determine if they've been sorted.
        if (0 === \count($assets[$asset]['dependencies'])) {
            $sorted[$asset] = $value;

            unset($assets[$asset]);
        } else {
            foreach ($assets[$asset]['dependencies'] as $key => $dependency) {
                if (!$this->dependency_is_valid($asset, $dependency, $original, $assets)) {
                    unset($assets[$asset]['dependencies'][$key]);

                    continue;
                }

                // If the dependency has not yet been added to the sorted list, we can not
                // remove it from this asset's array of dependencies. We'll try again on
                // the next trip through the loop.
                if (!isset($sorted[$dependency])) {
                    continue;
                }

                unset($assets[$asset]['dependencies'][$key]);
            }
        }
    }

    /**
     * Verify that an asset's dependency is valid.
     *
     * A dependency is considered valid if it exists, is not a circular reference, and is
     * not a reference to the owning asset itself. If the dependency doesn't exist, no
     * error or warning will be given. For the other cases, an exception is thrown.
     *
     * @param string $asset
     * @param string $dependency
     *
     * @return bool
     */
    protected function dependency_is_valid($asset, $dependency, array $original, array $assets)
    {
        if (!isset($original[$dependency])) {
            return false;
        }

        if ($dependency === $asset) {
            throw new \Exception("Asset [$asset] is dependent on itself.");
        }

        if (isset($assets[$dependency]) && \in_array($asset, $assets[$dependency]['dependencies'], true)) {
            throw new \Exception("Assets [$asset] and [$dependency] have a circular dependency.");
        }

        return true;
    }

    /**
     * Get the HTML link to a registered asset.
     *
     * @param string $group
     * @param string $name
     *
     * @return string
     */
    protected function asset($group, $name)
    {
        if (!isset($this->assets[$group][$name])) {
            return '';
        }

        $asset = $this->assets[$group][$name];

        // If the bundle source is not a complete URL, we will go ahead and prepend
        // the bundle's asset path to the source provided with the asset. This will
        // ensure that we attach the correct path to the asset.
        if (false === filter_var($asset['source'], \FILTER_VALIDATE_URL)) {
            $asset['source'] = $this->path($asset['source']);
        }

        return HTML::$group($asset['source'], $asset['attributes']);
    }
}
