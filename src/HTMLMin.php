<?php

namespace Aurora;

use Aurora\HTMLMin\Minifiers\BladeMinifier;
use Aurora\HTMLMin\Minifiers\CssMinifier;
use Aurora\HTMLMin\Minifiers\HtmlMinifier;
use Aurora\HTMLMin\Minifiers\JsMinifier;

class HTMLMin
{
    /**
     * The blade minifier instance.
     *
     * @var BladeMinifier
     */
    protected static $blade;

    /**
     * The css minifier instance.
     *
     * @var CssMinifier
     */
    protected static $css;

    /**
     * The js minifier instance.
     *
     * @var JsMinifier
     */
    protected static $js;

    /**
     * The html minifier instance.
     *
     * @var HtmlMinifier
     */
    protected static $html;

    /**
     * Get the minified blade.
     *
     * @param string $value
     *
     * @return string
     */
    public static function blade($value)
    {
        static::init();

        return self::$blade->render($value);
    }

    /**
     * Get the minified css.
     *
     * @param string $value
     *
     * @return string
     */
    public static function css($value)
    {
        static::init();

        return self::$css->render($value);
    }

    /**
     * Get the minified js.
     *
     * @param string $value
     *
     * @return string
     */
    public static function js($value)
    {
        static::init();

        return self::$js->render($value);
    }

    /**
     * Get the minified html.
     *
     * @param string $value
     *
     * @return string
     */
    public static function html($value)
    {
        static::init();

        return self::$html->render($value);
    }

    /**
     * Return the blade minifier instance.
     *
     * @return BladeMinifier
     */
    public static function getBladeMinifier()
    {
        static::init();

        return self::$blade;
    }

    /**
     * Return the css minifier instance.
     *
     * @return CssMinifier
     */
    public static function getCssMinifier()
    {
        static::init();

        return self::$css;
    }

    /**
     * Return the js minifier instance.
     *
     * @return JsMinifier
     */
    public static function getJsMinifier()
    {
        static::init();

        return self::$js;
    }

    /**
     * Return the html minifier instance.
     *
     * @return HtmlMinifier
     */
    public static function getHtmlMinifier()
    {
        static::init();

        return self::$html;
    }

    private static function init(): void
    {
        if (null === self::$html) {
            self::$blade = new BladeMinifier(false);
            self::$css = new CssMinifier();
            self::$js = new JsMinifier();
            self::$html = new HtmlMinifier(self::$css, self::$js);
        }
    }
}
