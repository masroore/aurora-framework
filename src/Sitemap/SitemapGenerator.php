<?php

namespace Aurora\Sitemap;

use Aurora\Cache;
use Aurora\Config;
use Aurora\File;
use Aurora\Response;
use Aurora\View;
use Carbon\Carbon;

class SitemapGenerator
{
    private const SITEMAP_INDEX = 'sitemapindex';

    /**
     * SitemapModel instance.
     *
     * @var SitemapModel
     */
    public $sitemapModel;

    /**
     * Using constructor we populate our model from configuration file
     * and loading dependencies.
     */
    public function __construct(array $config)
    {
        $this->sitemapModel = new SitemapModel($config);
    }

    /**
     * Set cache options.
     *
     * @param string               $key
     * @param Carbon|\Datetime|int $duration
     * @param bool                 $useCache
     */
    public function setCache($key = null, $duration = null, $useCache = true): void
    {
        $this->sitemapModel->setCacheEnabled($useCache);

        if (null !== $key) {
            $this->sitemapModel->setCacheKey($key);
        }

        if (null !== $duration) {
            $this->sitemapModel->setCacheDuration($duration);
        }
    }

    /**
     * Add new sitemap item to $items array.
     *
     * @param string $loc
     * @param string $lastmod
     * @param string $priority
     * @param string $freq
     * @param array  $images
     * @param string $title
     * @param array  $translations
     * @param array  $videos
     * @param array  $googlenews
     * @param array  $alternates
     */
    public function add($loc, $lastmod = null, $priority = null, $freq = null, $images = [], $title = null, $translations = [], $videos = [], $googlenews = [], $alternates = []): void
    {
        $params = [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'priority' => $priority,
            'freq' => $freq,
            'images' => $images,
            'title' => $title,
            'translations' => $translations,
            'videos' => $videos,
            'googlenews' => $googlenews,
            'alternates' => $alternates,
        ];

        $this->addItem($params);
    }

    /**
     * Add new sitemap one or multiple items to $items array.
     *
     * @param array $params
     */
    public function addItem($params = []): void
    {
        // if is multidimensional
        if (\array_key_exists(1, $params)) {
            foreach ($params as $a) {
                $this->addItem($a);
            }

            return;
        }

        // get params
        foreach ($params as $key => $value) {
            $$key = $value;
        }

        // set default values
        $loc ??= '/';
        $lastmod ??= null;
        $priority ??= null;
        $freq ??= null;
        $title ??= null;
        $images ??= [];
        $translations ??= [];
        $alternates ??= [];
        $videos ??= [];
        $googlenews ??= [];

        // escaping
        if ($this->sitemapModel->getEscaping()) {
            $loc = htmlentities($loc, \ENT_XML1);

            if (null !== $title) {
                htmlentities($title, \ENT_XML1);
            }

            if ($images) {
                foreach ($images as $k => $image) {
                    foreach ($image as $key => $value) {
                        $images[$k][$key] = htmlentities($value, \ENT_XML1);
                    }
                }
            }

            if ($translations) {
                foreach ($translations as $k => $translation) {
                    foreach ($translation as $key => $value) {
                        $translations[$k][$key] = htmlentities($value, \ENT_XML1);
                    }
                }
            }

            if ($alternates) {
                foreach ($alternates as $k => $alternate) {
                    foreach ($alternate as $key => $value) {
                        $alternates[$k][$key] = htmlentities($value, \ENT_XML1);
                    }
                }
            }

            if ($videos) {
                foreach ($videos as $k => $video) {
                    if (!empty($video['title'])) {
                        $videos[$k]['title'] = htmlentities($video['title'], \ENT_XML1);
                    }
                    if (!empty($video['description'])) {
                        $videos[$k]['description'] = htmlentities($video['description'], \ENT_XML1);
                    }
                }
            }

            if ($googlenews) {
                if (isset($googlenews['sitename'])) {
                    $googlenews['sitename'] = htmlentities($googlenews['sitename'], \ENT_XML1);
                }
            }
        }

        $googlenews['sitename'] ??= '';
        $googlenews['language'] ??= 'en';
        $googlenews['publication_date'] ??= date('Y-m-d H:i:s');

        $this->sitemapModel->setItems([
            'loc' => $loc,
            'lastmod' => $lastmod,
            'priority' => $priority,
            'freq' => $freq,
            'images' => $images,
            'title' => $title,
            'translations' => $translations,
            'videos' => $videos,
            'googlenews' => $googlenews,
            'alternates' => $alternates,
        ]);
    }

    /**
     * Add new sitemap to $sitemaps array.
     */
    public function resetSitemaps(array $sitemaps = []): void
    {
        $this->sitemapModel->resetSitemaps($sitemaps);
    }

    /**
     * Returns document with all sitemap items from $items array.
     *
     * @param string $format (options: xml, html, txt, ror-rss, ror-rdf, google-news)
     * @param string $style  (path to custom xls style like '/styles/xsl/xml-sitemap.xsl')
     *
     * @return Response|View
     */
    public function render($format = 'xml', $style = null)
    {
        // limit size of sitemap
        $maxSize = $this->sitemapModel->getMaxSize();
        if ($maxSize > 0 && \count($this->sitemapModel->getItems()) > $maxSize) {
            $this->sitemapModel->limitSize($maxSize);
        } elseif ('google-news' === $format && \count($this->sitemapModel->getItems()) > 1000) {
            $this->sitemapModel->limitSize(1000);
        } elseif ('google-news' !== $format && \count($this->sitemapModel->getItems()) > 50000) {
            $this->sitemapModel->limitSize();
        }

        $data = $this->generate($format, $style);

        return Response::make($data['content'], 200, $data['headers']);
    }

    /**
     * Generates document with all sitemap items from $items array.
     *
     * @param string $format (options: xml, html, txt, ror-rss, ror-rdf, sitemapindex, google-news)
     * @param string $style  (path to custom xls style like '/styles/xsl/xml-sitemap.xsl')
     */
    public function generate($format = 'xml', $style = null): ?array
    {
        // check if caching is enabled, there is a cached content and its duration isn't expired
        if ($this->isCached()) {
            (self::SITEMAP_INDEX === $format)
                ? $this->sitemapModel->resetSitemaps(Cache::get($this->sitemapModel->getCacheKey()))
                : $this->sitemapModel->resetItems(Cache::get($this->sitemapModel->getCacheKey()));
        } elseif ($this->sitemapModel->getCacheEnabled()) {
            (self::SITEMAP_INDEX === $format)
                ? Cache::put($this->sitemapModel->getCacheKey(), $this->sitemapModel->getSitemaps(), $this->sitemapModel->getCacheDuration())
                : Cache::put($this->sitemapModel->getCacheKey(), $this->sitemapModel->getItems(), $this->sitemapModel->getCacheDuration());
        }

        if (!$this->sitemapModel->getLink()) {
            $this->sitemapModel->setLink(Config::get('app.url'));
        }

        if (!$this->sitemapModel->getTitle()) {
            $this->sitemapModel->setTitle('SitemapGenerator for ' . $this->sitemapModel->getLink());
        }

        $channel = [
            'title' => $this->sitemapModel->getTitle(),
            'link' => $this->sitemapModel->getLink(),
        ];

        // check if styles are enabled
        if ($this->sitemapModel->getStylesEnabled()) {
            if (null !== $this->sitemapModel->getStylesLocation() && View::exists($this->sitemapModel->getStylesLocation() . $format . '.xsl')) {
                // use style from your custom location
                $style = $this->sitemapModel->getStylesLocation() . $format . '.xsl';
            } else {
                // don't use style
                $style = null;
            }
        } else {
            // don't use style
            $style = null;
        }

        switch ($format) {
            case 'ror-rss':
                return $this->generateView($format, $channel, $style, 'rss+xml');
            case 'ror-rdf':
                return $this->generateView($format, $channel, $style, 'rdf+xml');
            case 'html':
                return $this->generateView($format, $channel, $style, 'html');
            case 'txt':
                return $this->generateView($format, null, $style, 'plain');
            default:
                return $this->generateView($format, null, $style, 'xml');
        }
    }

    /**
     * Checks if content is cached.
     *
     * @return bool
     */
    public function isCached()
    {
        if ($this->sitemapModel->getCacheEnabled()) {
            if (Cache::has($this->sitemapModel->getCacheKey())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate sitemap and store it to a file.
     *
     * @param string $format   (options: xml, html, txt, ror-rss, ror-rdf, sitemapindex, google-news)
     * @param string $filename (without file extension, may be a path like 'sitemaps/sitemap1' but must exist)
     * @param string $path     (path to store sitemap like '/www/site/public')
     * @param string $style    (path to custom xls style like '/styles/xsl/xml-sitemap.xsl')
     */
    public function store($format = 'xml', $filename = 'sitemap', $path = null, $style = null): void
    {
        // turn off caching for this method
        $this->sitemapModel->setCacheEnabled(false);

        // use correct file extension
        \in_array($format, ['txt', 'html'], true) ? $fe = $format : $fe = 'xml';

        if ($this->sitemapModel->getUseGzip()) {
            $fe .= '.gz';
        }

        // use custom size limit for sitemaps
        $maxSize = $this->sitemapModel->getMaxSize();
        if ($maxSize > 0 && \count($this->sitemapModel->getItems()) > $maxSize) {
            if ($this->sitemapModel->getUseLimitSize()) {
                // limit size
                $this->sitemapModel->limitSize($maxSize);
                $data = $this->generate($format, $style);
            } else {
                // use sitemapindex and generate partial sitemaps
                foreach (array_chunk($this->sitemapModel->getItems(), $maxSize) as $key => $item) {
                    // reset current items
                    $this->sitemapModel->resetItems($item);

                    // generate new partial sitemap
                    $this->store($format, $filename . '-' . $key, $path, $style);

                    // add sitemap to sitemapindex
                    if (null !== $path) {
                        // if using custom path generate relative urls for sitemaps in the sitemapindex
                        $this->addSitemap($filename . '-' . $key . '.' . $fe);
                    } else {
                        // else generate full urls based on app's domain
                        $this->addSitemap(url($filename . '-' . $key . '.' . $fe));
                    }
                }

                $data = $this->generate(self::SITEMAP_INDEX, $style);
            }
        } elseif (('google-news' !== $format && \count($this->sitemapModel->getItems()) > 50000) || ('google-news' === $format && \count($this->sitemapModel->getItems()) > 1000)) {
            ('google-news' !== $format) ? $max = 50000 : $max = 1000;

            // check if limiting size of items array is enabled
            if (!$this->sitemapModel->getUseLimitSize()) {
                // use sitemapindex and generate partial sitemaps
                foreach (array_chunk($this->sitemapModel->getItems(), $max) as $key => $item) {
                    // reset current items
                    $this->sitemapModel->resetItems($item);

                    // generate new partial sitemap
                    $this->store($format, $filename . '-' . $key, $path, $style);

                    // add sitemap to sitemapindex
                    if (null === $path) {
                        // else generate full urls based on app's domain
                        $this->addSitemap(url($filename . '-' . $key . '.' . $fe));
                    } else {
                        // if using custom path generate relative urls for sitemaps in the sitemapindex
                        $this->addSitemap($filename . '-' . $key . '.' . $fe);
                    }
                }

                $data = $this->generate(self::SITEMAP_INDEX, $style);
            } else {
                // reset items and use only most recent $max items
                $this->sitemapModel->limitSize($max);
                $data = $this->generate($format, $style);
            }
        } else {
            $data = $this->generate($format, $style);
        }

        // clear memory
        if (self::SITEMAP_INDEX === $format) {
            $this->sitemapModel->resetSitemaps();
        }

        $this->sitemapModel->resetItems();

        // if custom path
        if (null === $path) {
            $file = storage_path($filename . '.' . $fe);
        } else {
            $file = $path . DS . $filename . '.' . $fe;
        }

        if ($this->sitemapModel->getUseGzip()) {
            // write file (gzip compressed)
            File::put($file, gzencode($data['content'], 9));
        } else {
            // write file
            File::put($file, $data['content']);
        }
    }

    /**
     * Add new sitemap to $sitemaps array.
     *
     * @param string $loc
     * @param string $lastmod
     */
    public function addSitemap($loc, $lastmod = null): void
    {
        $this->sitemapModel->setSitemaps([
            'loc' => $loc,
            'lastmod' => $lastmod,
        ]);
    }

    private function generateView($view, $channel, $style, $content_type)
    {
        return [
            'content' => View::make('vendor.sitemap.' . $view, ['items' => $this->sitemapModel->getItems(), 'channel' => $channel, 'style' => $style])->render(),
            'headers' => ['Content-type' => "text/{$content_type}; charset=utf-8"], ];
    }
}
