<?php

namespace Aurora\HTMLMin\Minifiers;

class BladeMinifier implements MinifierInterface
{
    /**
     * Should minification be forcefully enabled.
     *
     * @var bool
     */
    protected $force;

    /**
     * Create a new instance.
     *
     * @param bool $force
     */
    public function __construct($force)
    {
        $this->force = $force;
    }

    /**
     * Get the minified value.
     *
     * All credit to Trevor Fitzgerald for the regex here.
     * See the original here: http://bit.ly/U7mv7a.
     *
     * @param string $value
     *
     * @return string
     */
    public function render($value)
    {
        if ($this->shouldMinify($value)) {
            $replace = [
                '/<!--[^\[](.*?)[^\]]-->/s' => '',
                '/<\\?php/' => '<?php ',
                "/\n([\\S])/" => ' $1',
                "/\r/" => '',
                "/\n/" => '',
                "/\t/" => ' ',
                '/ +/' => ' ',
            ];

            $value = preg_replace(array_keys($replace), array_values($replace), $value);
        } else {
            // Where skip minification tags are used let's remove them from markdown or blade.
            $value = preg_replace('/<!--[\\s]+skip\\.minification[\\s]+-->/', '', $value);
        }

        return $value;
    }

    /**
     * Determine if the blade should be minified.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function shouldMinify($value)
    {
        if ($this->force) {
            return true;
        }

        return !$this->containsBadHtml($value) && !$this->containsBadComments($value);
    }

    /**
     * Does the code contain bad html?
     *
     * @param string $value
     *
     * @return bool
     */
    protected function containsBadHtml($value)
    {
        return preg_match('/<(code|pre|textarea)/', $value)
            || preg_match('/<script[^\??>]*>[^<\/script>]/', $value)
            || preg_match('/<!--[\s]+skip\.minification[\s]+-->/', $value)
            || preg_match('/value=("|\')(.*)([ ]{2,})(.*)("|\')/', $value);
    }

    /**
     * Does the code contain bad comments?
     *
     * @param string $value
     *
     * @return bool
     */
    protected function containsBadComments($value)
    {
        foreach (token_get_all($value) as $token) {
            if (!\is_array($token) || !isset($token[0]) || \T_COMMENT !== $token[0]) {
                continue;
            }

            if ('//' === mb_substr($token[1], 0, 2)) {
                return true;
            }
        }

        return false;
    }
}
