<?php

namespace Aurora\HTMLMin\Minifiers;

/**
 * This is the css minifier class.
 */
class CssMinifier implements MinifierInterface
{
    /**
     * Get the minified value.
     *
     * @param string $value
     *
     * @return string
     */
    public function render($value)
    {
        return \Minify_CSS::minify($value, ['preserveComments' => false]);
    }
}
