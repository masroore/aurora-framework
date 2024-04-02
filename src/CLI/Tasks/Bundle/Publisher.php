<?php

namespace Aurora\CLI\Tasks\Bundle;

use Aurora\Bundle;
use Aurora\File;

class Publisher
{
    /**
     * Publish a bundle's assets to the public directory.
     *
     * @param string $bundle
     */
    public function publish($bundle): void
    {
        if (!Bundle::exists($bundle)) {
            echo "Bundle [$bundle] is not registered.";

            return;
        }

        $path = Bundle::path($bundle);

        $this->move($path . 'public', PUBLIC_PATH . 'bundles' . DS . $bundle);

        echo "Assets published for bundle [$bundle]." . \PHP_EOL;
    }

    /**
     * Delete a bundle's assets from the public directory.
     *
     * @param string $bundle
     */
    public function unpublish($bundle): void
    {
        if (!Bundle::exists($bundle)) {
            echo "Bundle [$bundle] is not registered.";

            return;
        }

        File::rmdir(PUBLIC_PATH . 'bundles' . DS . $bundle);

        echo "Assets deleted for bundle [$bundle]." . \PHP_EOL;
    }

    /**
     * Copy the contents of a bundle's assets to the public folder.
     *
     * @param string $source
     * @param string $destination
     */
    protected function move($source, $destination): void
    {
        File::cpdir($source, $destination);
    }

    /**
     * Get the "to" location of the bundle's assets.
     *
     * @param string $bundle
     *
     * @return string
     */
    protected function to($bundle)
    {
        return PUBLIC_PATH . 'bundles' . DS . $bundle . DS;
    }

    /**
     * Get the "from" location of the bundle's assets.
     *
     * @param string $bundle
     *
     * @return string
     */
    protected function from($bundle)
    {
        return Bundle::path($bundle) . 'public';
    }
}
