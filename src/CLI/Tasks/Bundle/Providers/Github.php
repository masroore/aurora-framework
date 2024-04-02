<?php

namespace Aurora\CLI\Tasks\Bundle\Providers;

class Github extends Provider
{
    /**
     * Install the given bundle into the application.
     *
     * @param string $bundle
     * @param string $path
     */
    public function install($bundle, $path): void
    {
        $url = "http://github.com/{$bundle['location']}/zipball/master";

        parent::zipball($url, $bundle, $path);
    }
}
