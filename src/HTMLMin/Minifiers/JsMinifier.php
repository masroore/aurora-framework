<?php

namespace Aurora\HTMLMin\Minifiers;

use JSMin\JSMin;

class JsMinifier implements MinifierInterface
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
        return JSMin::minify($value);
    }
}
