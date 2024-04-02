<?php

namespace Aurora\Sitemap;

use Carbon\Carbon;

class SitemapModel
{
    /**
     * @var bool
     */
    public $testing = false;

    /**
     * @var array
     */
    private $items = [];

    /**
     * @var array
     */
    private $sitemaps = [];

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $link;

    /**
     * Enable or disable xsl styles.
     *
     * @var bool
     */
    private $stylesEnabled = true;

    /**
     * Set custom location for xsl styles (must end with slash).
     *
     * @var string
     */
    private $stylesLocation = 'vendor.sitemap.styles';

    /**
     * Enable or disable cache.
     *
     * @var bool
     */
    private $cacheEnabled = false;

    /**
     * Unique cache key.
     *
     * @var string
     */
    private $cacheKey = 'aurora-sitemap.';

    /**
     * Cache duration, can be int or timestamp.
     *
     * @var Carbon|\Datetime|int
     */
    private $cacheDuration = 3600;

    /**
     * Escaping html entities.
     *
     * @var bool
     */
    private $escaping = true;

    /**
     * Use limitSize() for big sitemaps.
     *
     * @var bool
     */
    private $useLimitSize = false;

    /**
     * Custom max size for limitSize().
     *
     * @var bool
     */
    private $maxSize;

    /**
     * Use gzip compression.
     *
     * @var bool
     */
    private $useGzip = false;

    /**
     * Populating model variables from configuation file.
     */
    public function __construct(array $config)
    {
        $this->cacheEnabled = $config['cache_enabled'] ?? $this->cacheEnabled;
        $this->cacheKey = $config['cache_key'] ?? $this->cacheKey;
        $this->cacheDuration = $config['cache_duration'] ?? $this->cacheDuration;
        $this->escaping = $config['escaping'] ?? $this->escaping;
        $this->useLimitSize = $config['use_limit_size'] ?? $this->useLimitSize;
        $this->stylesEnabled = $config['styles_enabled'] ?? $this->stylesEnabled;
        $this->stylesLocation = $config['styles_location'] ?? $this->stylesLocation;
        $this->maxSize = $config['max_size'] ?? $this->maxSize;
        $this->testing = $config['testing'] ?? $this->testing;
        $this->useGzip = $config['use_gzip'] ?? $this->useGzip;
    }

    /**
     * Returns $items array.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Returns $sitemaps array.
     *
     * @return array
     */
    public function getSitemaps()
    {
        return $this->sitemaps;
    }

    /**
     * Returns $title value.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Returns $link value.
     *
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * Returns $useStyles value.
     *
     * @return bool
     */
    public function getStylesEnabled()
    {
        return $this->stylesEnabled;
    }

    /**
     * Returns $sloc value.
     *
     * @return string
     */
    public function getStylesLocation()
    {
        return $this->stylesLocation;
    }

    /**
     * Returns $useCache value.
     *
     * @return bool
     */
    public function getCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    /**
     * Returns $CacheKey value.
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cacheKey;
    }

    /**
     * Returns $CacheDuration value.
     *
     * @return string
     */
    public function getCacheDuration()
    {
        return $this->cacheDuration;
    }

    /**
     * Returns $escaping value.
     *
     * @return bool
     */
    public function getEscaping()
    {
        return $this->escaping;
    }

    /**
     * Returns $useLimitSize value.
     *
     * @return bool
     */
    public function getUseLimitSize()
    {
        return $this->useLimitSize;
    }

    /**
     * Returns $maxSize value.
     *
     * @return int
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * Returns $useGzip value.
     *
     * @return bool
     */
    public function getUseGzip()
    {
        return $this->useGzip;
    }

    /**
     * Sets $escaping value.
     *
     * @param bool $escaping
     */
    public function setEscaping($escaping): void
    {
        $this->escaping = $escaping;
    }

    /**
     * Adds item to $items array.
     */
    public function setItems(array $items): void
    {
        $this->items[] = $items;
    }

    /**
     * Adds sitemap to $sitemaps array.
     */
    public function setSitemaps(array $sitemap): void
    {
        $this->sitemaps[] = $sitemap;
    }

    /**
     * Sets $title value.
     *
     * @param string $title
     */
    public function setTitle($title): void
    {
        $this->title = $title;
    }

    /**
     * Sets $link value.
     *
     * @param string $link
     */
    public function setLink($link): void
    {
        $this->link = $link;
    }

    /**
     * Sets $useStyles value.
     *
     * @param bool $stylesEnabled
     */
    public function setStylesEnabled($stylesEnabled): void
    {
        $this->stylesEnabled = $stylesEnabled;
    }

    /**
     * Sets $sloc value.
     *
     * @param string $stylesLocation
     */
    public function setStylesLocation($stylesLocation): void
    {
        $this->stylesLocation = $stylesLocation;
    }

    /**
     * Sets $useLimitSize value.
     *
     * @param bool $useLimitSize
     */
    public function setUseLimitSize($useLimitSize): void
    {
        $this->useLimitSize = $useLimitSize;
    }

    /**
     * Sets $maxSize value.
     *
     * @param int $maxSize
     */
    public function setMaxSize($maxSize): void
    {
        $this->maxSize = $maxSize;
    }

    /**
     * Sets $useGzip value.
     *
     * @param bool $useGzip
     */
    public function setUseGzip($useGzip = true): void
    {
        $this->useGzip = $useGzip;
    }

    /**
     * Limit size of $items array to 50000 elements (1000 for google-news).
     *
     * @param int $max
     */
    public function limitSize($max = 50000): void
    {
        $this->items = \array_slice($this->items, 0, $max);
    }

    /**
     * Reset $items array.
     */
    public function resetItems(array $items = []): void
    {
        $this->items = $items;
    }

    /**
     * Reset $sitemaps array.
     *
     * @param array $sitemaps
     */
    public function resetSitemaps($sitemaps = []): void
    {
        $this->sitemaps = $sitemaps;
    }

    /**
     * Set use cache value.
     *
     * @param bool $cacheEnabled
     */
    public function setCacheEnabled($cacheEnabled = true): void
    {
        $this->cacheEnabled = $cacheEnabled;
    }

    /**
     * Set cache key value.
     *
     * @param string $cacheKey
     */
    public function setCacheKey($cacheKey): void
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * Set cache duration value.
     *
     * @param Carbon|\Datetime|int $cacheDuration
     */
    public function setCacheDuration($cacheDuration): void
    {
        $this->cacheDuration = $cacheDuration;
    }
}
