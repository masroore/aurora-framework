<?php

namespace Aurora\HTMLMin\Minifiers;

class HtmlMinifier implements MinifierInterface
{
    /**
     * The css minifier instance.
     *
     * @var CssMinifier
     */
    protected $css;

    /**
     * The js minifier instance.
     *
     * @var JsMinifier
     */
    protected $js;

    /**
     * Create a new instance.
     */
    public function __construct(CssMinifier $css, JsMinifier $js)
    {
        $this->css = $css;
        $this->js = $js;
    }

    /**
     * Get the minified value.
     *
     * @param string $value
     *
     * @return string
     */
    public function render($value)
    {
        $options = [
            'cssMinifier' => fn ($css) => $this->css->render($css),
            'jsMinifier' => fn ($js) => $this->js->render($js),
            'jsCleanComments' => true,
        ];

        return \Minify_HTML::minify($value, $options);
    }

    /**
     * Return the css minifier instance.
     *
     * @return CssMinifier
     */
    public function getCssMinifier()
    {
        return $this->css;
    }

    /**
     * Return the js minifier instance.
     *
     * @return JsMinifier
     */
    public function getJsMinifier()
    {
        return $this->js;
    }
}
