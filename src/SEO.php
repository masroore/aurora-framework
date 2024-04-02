<?php

namespace Aurora;

use Aurora\SEO\OpenGraph;
use Aurora\SEO\SEOMeta;
use Aurora\SEO\TwitterCards;

class SEO
{
    private static $metatags;

    private static $opengraph;

    private static $twitter;

    /**
     * @return SEOMeta
     */
    public static function metatags()
    {
        if (!isset(self::$metatags)) {
            self::$metatags = new SEOMeta(config('seo.meta'));
        }

        return self::$metatags;
    }

    /**
     * @return OpenGraph
     */
    public static function opengraph()
    {
        if (!isset(self::$opengraph)) {
            self::$opengraph = new OpenGraph(config('seo.opengraph'));
        }

        return self::$opengraph;
    }

    /**
     * @return TwitterCards
     */
    public static function twitter()
    {
        if (!isset(self::$twitter)) {
            self::$twitter = new TwitterCards(config('seo.twitter'));
        }

        return self::$twitter;
    }

    /**
     * Setup title for all seo providers.
     *
     * @param string $title
     * @param bool   $appendDefault
     */
    public static function setTitle($title, $appendDefault = true): void
    {
        self::metatags()->setTitle($title, $appendDefault);
        self::opengraph()->setTitle($title);
        self::twitter()->setTitle($title);
    }

    /**
     * Setup description for all seo providers.
     */
    public static function setDescription($description): void
    {
        self::metatags()->setDescription($description);
        self::opengraph()->setDescription($description);
        self::twitter()->setDescription($description);
    }

    /**
     * Sets the canonical URL.
     *
     * @param string $url
     */
    public static function setCanonical($url): void
    {
        self::metatags()->setCanonical($url);
    }

    /**
     * @param array|string $urls
     */
    public static function addImages($urls): void
    {
        if (\is_array($urls)) {
            self::opengraph()->addImages($urls);
        } else {
            self::opengraph()->addImage($urls);
        }

        self::twitter()->addImage($urls);
    }

    /**
     * Get current title from metatags.
     *
     * @param bool $session
     *
     * @return string
     */
    public static function getTitle($session = false)
    {
        if ($session) {
            return self::metatags()->getTitleSession();
        }

        return self::metatags()->getTitle();
    }

    /**
     * Generate from all seo providers.
     *
     * @param bool $minify
     *
     * @return string
     */
    public static function generate($minify = false)
    {
        $html = self::metatags()->generate();
        $html .= \PHP_EOL;
        $html .= self::opengraph()->generate();
        $html .= \PHP_EOL;
        $html .= self::twitter()->generate();

        return ($minify) ? str_replace(\PHP_EOL, '', $html) : $html;
    }
}
