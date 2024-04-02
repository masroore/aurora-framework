<?php

namespace Aurora;

use Aurora\Sitemap\SitemapGenerator;

class Sitemap
{
    /**
     * @var SitemapGenerator
     */
    private static $sitemapGenerator;

    public static function __callStatic($method, $parameters)
    {
        if (null === self::$sitemapGenerator) {
            self::boot();
        }

        return \call_user_func_array([self::$sitemapGenerator, $method], $parameters);
    }

    public static function boot(): void
    {
        self::$sitemapGenerator = new SitemapGenerator(Config::get('sitemap'));
    }
}
