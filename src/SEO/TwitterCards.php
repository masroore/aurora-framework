<?php

namespace Aurora\SEO;

class TwitterCards
{
    /**
     * @var string
     */
    protected $prefix = 'twitter:';

    /**
     * @var array
     */
    protected $html = [];

    /**
     * @var array
     */
    protected $values = [];

    /**
     * @var array
     */
    protected $images = [];

    public function __construct(array $defaults = [])
    {
        $this->values = $defaults;
    }

    /**
     * @param bool $minify
     *
     * @return string
     */
    public function generate($minify = false)
    {
        $this->eachValue($this->values);
        $this->eachValue($this->images, 'images');

        return ($minify) ? implode('', $this->html) : implode(\PHP_EOL, $this->html);
    }

    /**
     * @param string       $key
     * @param array|string $value
     *
     * @return TwitterCards
     */
    public function addValue($key, $value)
    {
        $this->values[$key] = $value;

        return $this;
    }

    /**
     * @param string $title
     *
     * @return TwitterCards
     */
    public function setTitle($title)
    {
        return $this->addValue('title', $title);
    }

    /**
     * @param string $type
     *
     * @return TwitterCards
     */
    public function setType($type)
    {
        return $this->addValue('card', $type);
    }

    /**
     * @param string $site
     *
     * @return TwitterCards
     */
    public function setSite($site)
    {
        return $this->addValue('site', $site);
    }

    /**
     * @param string $description
     *
     * @return TwitterCards
     */
    public function setDescription($description)
    {
        return $this->addValue('description', htmlentities($description));
    }

    /**
     * @param string $url
     *
     * @return TwitterCards
     */
    public function setUrl($url)
    {
        return $this->addValue('url', $url);
    }

    /**
     * @param array|string $image
     *
     * @return TwitterCards
     *
     * @deprecated use setImage($image) instead
     */
    public function addImage($image)
    {
        foreach ((array)$image as $url) {
            $this->images[] = $url;
        }

        return $this;
    }

    /**
     * @param array|string $images
     *
     * @return TwitterCards
     *
     * @deprecated use setImage($image) instead
     */
    public function setImages($images)
    {
        $this->images = [];

        return $this->addImage($images);
    }

    /**
     * @return TwitterCards
     */
    public function setImage($image)
    {
        return $this->addValue('image', $image);
    }

    /**
     * Make tags.
     *
     * @param string|null $prefix
     *
     * @internal param array $properties
     */
    protected function eachValue(array $values, $prefix = null): void
    {
        foreach ($values as $key => $value) {
            if (\is_array($value)) {
                $this->eachValue($value, $key);
            } else {
                if (is_numeric($key)) {
                    $key = $prefix . $key;
                } elseif (\is_string($prefix)) {
                    $key = $prefix . ':' . $key;
                }

                $this->html[] = $this->makeTag($key, $value);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return string
     *
     * @internal param string $values
     */
    private function makeTag($key, $value)
    {
        return '<meta name="' . $this->prefix . strip_tags($key) . '" content="' . strip_tags($value) . '" />';
    }
}
