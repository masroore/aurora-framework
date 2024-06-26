<?php

namespace Aurora\CLI\Tasks\Bundle;

\defined('DS') || exit('No direct script access.');

use Aurora\Bundle;
use Aurora\CLI\Tasks\Task;
use Aurora\File;
use Aurora\IoC;

class Bundler extends Task
{
    /**
     * The bundle API repository.
     *
     * @var Repository
     */
    protected $repository;

    /**
     * Create a new bundle manager task.
     *
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Install the given bundles into the application.
     *
     * @param array $bundles
     */
    public function install($bundles): void
    {
        foreach ($this->get($bundles) as $bundle) {
            if (Bundle::exists($bundle['name'])) {
                echo "Bundle {$bundle['name']} is already installed.";

                continue;
            }

            // Once we have the bundle information, we can resolve an instance
            // of a provider and install the bundle into the application and
            // all of its registered dependencies as well.
            //
            // Each bundle provider implements the Provider interface and
            // is responsible for retrieving the bundle source from its
            // hosting party and installing it into the application.
            $path = path('bundle') . $this->path($bundle);

            echo "Fetching [{$bundle['name']}]...";

            $this->download($bundle, $path);

            echo 'done! Bundle installed.' . \PHP_EOL;
        }
    }

    /**
     * Uninstall the given bundles from the application.
     *
     * @param array $bundles
     */
    public function uninstall($bundles): void
    {
        if (0 === \count($bundles)) {
            throw new \Exception('Tell me what bundle to uninstall.');
        }

        foreach ($bundles as $name) {
            if (!Bundle::exists($name)) {
                echo "Bundle [{$name}] is not installed.";

                continue;
            }

            echo "Uninstalling [{$name}]..." . \PHP_EOL;
            $migrator = IoC::resolve('task: migrate');
            $migrator->reset($name);

            $publisher = IoC::resolve('bundle.publisher');
            $publisher->unpublish($name);

            $location = Bundle::path($name);
            File::rmdir($location);

            echo "Bundle [{$name}] has been uninstalled!" . \PHP_EOL;
        }

        echo 'Now, you have to remove those bundle from your application/bundles.php' . \PHP_EOL;
    }

    /**
     * Upgrade the given bundles for the application.
     *
     * @param array $bundles
     */
    public function upgrade($bundles): void
    {
        if (0 === \count($bundles)) {
            $bundles = Bundle::names();
        }

        foreach ($bundles as $name) {
            if (!Bundle::exists($name)) {
                echo "Bundle [{$name}] is not installed!";

                continue;
            }

            // First we want to retrieve the information for the bundle, such as
            // where it is currently installed. This will allow us to upgrade
            // the bundle into it's current installation path.
            $location = Bundle::path($name);

            // If the bundle exists, we will grab the data about the bundle from
            // the API so we can make the right bundle provider for the bundle,
            // since we don't know the provider used to install.
            $response = $this->retrieve($name);

            if ('not-found' === $response['status']) {
                continue;
            }

            // Once we have the bundle information from the API, we'll simply
            // recursively delete the bundle and then re-download it using
            // the correct provider assigned to the bundle.
            File::rmdir($location);

            $this->download($response['bundle'], $location);

            echo "Bundle [{$name}] has been upgraded!" . \PHP_EOL;
        }
    }

    /**
     * Publish bundle assets to the public directory.
     *
     * @param array $bundles
     */
    public function publish($bundles): void
    {
        if (0 === \count($bundles)) {
            $bundles = Bundle::names();
        }

        array_walk($bundles, [IoC::resolve('bundle.publisher'), 'publish']);
    }

    /**
     * Delete bundle assets from the public directory.
     *
     * @param array $bundles
     */
    public function unpublish($bundles): void
    {
        if (0 === \count($bundles)) {
            $bundles = Bundle::names();
        }

        array_walk($bundles, [IoC::resolve('bundle.publisher'), 'unpublish']);
    }

    /**
     * Gather all of the bundles from the bundle repository.
     *
     * @param array $bundles
     *
     * @return array
     */
    protected function get($bundles)
    {
        $responses = [];

        foreach ($bundles as $bundle) {
            // First we'll call the bundle repository to gather the bundle data
            // array, which contains all of the information needed to install
            // the bundle into the Aurora application.
            $response = $this->retrieve($bundle);

            if ('not-found' === $response['status']) {
                throw new \Exception("There is no bundle named [$bundle].");
            }

            // If the bundle was retrieved successfully, we will add it to
            // our array of bundles, as well as merge all of the bundle's
            // dependencies into the array of responses.
            $bundle = $response['bundle'];

            $responses[] = $bundle;

            // We'll also get the bundle's declared dependencies so they
            // can be installed along with the bundle, making it easy
            // to install a group of bundles.
            $dependencies = $this->get($bundle['dependencies']);

            $responses = array_merge($responses, $dependencies);
        }

        return $responses;
    }

    /**
     * Retrieve a bundle from the repository.
     *
     * @param string $bundle
     *
     * @return array
     */
    protected function retrieve($bundle)
    {
        $response = $this->repository->get($bundle);

        if (!$response) {
            throw new \Exception('The bundle API is not responding.');
        }

        return $response;
    }

    /**
     * Return the path for a given bundle.
     *
     * @param array $bundle
     *
     * @return string
     */
    protected function path($bundle)
    {
        return array_get($bundle, 'path', $bundle['name']);
    }

    /**
     * Install a bundle using a provider.
     *
     * @param string $bundle
     * @param string $path
     */
    protected function download($bundle, $path): void
    {
        $provider = "bundle.provider: {$bundle['provider']}";

        IoC::resolve($provider)->install($bundle, $path);
    }
}
