<?php

namespace Aurora;

class HTML
{
    /**
     * The registered custom macros.
     */
    public static array $macros = [];

    /**
     * Cache application encoding locally to save expensive calls to config::get().
     */
    public static ?string $encoding = null;

    /**
     * Dynamically handle calls to custom macros.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (isset(static::$macros[$method])) {
            return \call_user_func_array(static::$macros[$method], $parameters);
        }

        throw new \Exception("Method [$method] does not exist.");
    }

    /**
     * Registers a custom macro.
     */
    public static function macro(string $name, \Closure $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Convert entities to HTML characters.
     */
    public static function decode(string $value): string
    {
        return html_entity_decode($value, \ENT_QUOTES, static::encoding());
    }

    /**
     * Get the appliction.encoding without needing to request it from Config::get() each time.
     *
     * @return string
     */
    protected static function encoding()
    {
        return static::$encoding ?: static::$encoding = Config::get('app.encoding');
    }

    /**
     * Convert HTML special characters.
     *
     * The encoding specified in the application configuration file will be used.
     */
    public static function specialchars(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, static::encoding(), false);
    }

    /**
     * Generate a link to a JavaScript file.
     *
     * <code>
     *        // Generate a link to a JavaScript file
     *        echo HTML::script('js/jquery.js');
     *
     *        // Generate a link to a JavaScript file and add some attributes
     *        echo HTML::script('js/jquery.js', array('defer'));
     * </code>
     *
     * @param bool $https
     */
    public static function script(string $url, array $attributes = [], $https = null): string
    {
        $attributes['src'] = Url::asset($url, $https);

        return '<script ' . static::attributes($attributes) . '></script>' . \PHP_EOL;
    }

    /**
     * Generate an HTML link to an asset.
     *
     * The application index page will not be added to asset links.
     *
     * @param string $url
     * @param string $title
     * @param array  $attributes
     * @param bool   $https
     *
     * @return string
     */
    public static function asset($url, $title = null, $attributes = [], $https = null, $escape = true)
    {
        $url = Url::asset($url, $https);

        if (null === $title) {
            $title = $url;
        }

        if ($escape) {
            $title = static::entities($title);
        }

        return '<a href="' . $url . '"' . static::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Convert HTML characters to entities.
     *
     * The encoding specified in the application configuration file will be used.
     */
    public static function entities(?string $value): string
    {
        return htmlentities($value ?? '', \ENT_QUOTES, static::encoding(), false);
    }

    /**
     * Build a list of HTML attributes from an array.
     */
    public static function attributes(array $attributes): string
    {
        $html = [];

        foreach ((array)$attributes as $key => $value) {
            $element = static::attributeElement($key, $value);

            if (null !== $element) {
                $html[] = $element;
            }
        }

        return (\count($html) > 0) ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Build a single attribute element.
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    protected static function attributeElement($key, $value)
    {
        // For numeric keys we will assume that the value is a boolean attribute
        // where the presence of the attribute represents a true value and the
        // absence represents a false value.
        // This will convert HTML attributes such as "required" to a correct
        // form instead of using incorrect numerics.
        if (is_numeric($key)) {
            return $value;
        }

        // Treat boolean attributes as HTML properties
        if (\is_bool($value) && 'value' !== $key) {
            return $value ? $key : '';
        }

        if (\is_array($value) && 'class' === $key) {
            return 'class="' . implode(' ', $value) . '"';
        }

        if (null !== $value) {
            return $key . '="' . e($value) . '"';
        }
    }

    /**
     * Generate a link to a CSS file.
     *
     * If no media type is selected, "all" will be used.
     *
     * <code>
     *        // Generate a link to a CSS file
     *        echo HTML::style('css/common.css');
     *
     *        // Generate a link to a CSS file and add some attributes
     *        echo HTML::style('css/common.css', array('media' => 'print'));
     * </code>
     *
     * @param string $url
     * @param array  $attributes
     *
     * @return string
     */
    public static function style($url, $attributes = [])
    {
        $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

        $attributes += $defaults;

        $url = Url::asset($url);

        return '<link href="' . $url . '"' . static::attributes($attributes) . '>' . \PHP_EOL;
    }

    /**
     * Generate a HTML span.
     *
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function span($value, $attributes = [])
    {
        return '<span' . static::attributes($attributes) . '>' . static::entities($value) . '</span>';
    }

    /**
     * Generate a HTTPS HTML link.
     *
     * @param string $url
     * @param string $title
     * @param array  $attributes
     *
     * @return string
     */
    public static function secure_link($url, $title = null, $attributes = [])
    {
        return static::link($url, $title, $attributes, true);
    }

    /**
     * Generate a HTML link.
     *
     * <code>
     *        // Generate a link to a location within the application
     *        echo HTML::link('user/profile', 'User Profile');
     *
     *        // Generate a link to a location outside of the application
     *        echo HTML::link('http://google.com', 'Google');
     * </code>
     *
     * @param string $url
     * @param string $title
     * @param array  $attributes
     * @param bool   $https
     * @param bool   $escape
     *
     * @return string
     */
    public static function link($url, $title = null, $attributes = [], $https = null, $escape = true)
    {
        $url = Url::to($url, $https);

        if (null === $title) {
            $title = $url;
        }

        if ($escape) {
            $title = static::entities($title);
        }

        return '<a href="' . $url . '"' . static::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Generate an HTTPS HTML link to an asset.
     *
     * @param string $url
     * @param string $title
     * @param array  $attributes
     *
     * @return string
     */
    public static function secure_asset($url, $title = null, $attributes = [])
    {
        return static::asset($url, $title, $attributes, true);
    }

    /**
     * Generate an HTML link to a route.
     *
     * An array of parameters may be specified to fill in URI segment wildcards.
     *
     * <code>
     *        // Generate a link to the "profile" named route
     *        echo HTML::route('profile', 'Profile');
     *
     *        // Generate a link to the "profile" route and add some parameters
     *        echo HTML::route('profile', 'Profile', array('taylor'));
     * </code>
     *
     * @param string $name
     * @param string $title
     * @param array  $parameters
     * @param array  $attributes
     *
     * @return string
     */
    public static function route($name, $title = null, $parameters = [], $attributes = [])
    {
        return static::link(Url::route($name, $parameters), $title, $attributes);
    }

    /**
     * Generate an HTML link to a controller action.
     *
     * An array of parameters may be specified to fill in URI segment wildcards.
     *
     * <code>
     *        // Generate a link to the "home@index" action
     *        echo HTML::link_to_action('home@index', 'Home');
     *
     *        // Generate a link to the "user@profile" route and add some parameters
     *        echo HTML::link_to_action('user@profile', 'Profile', array('taylor'));
     * </code>
     *
     * @param string $action
     * @param string $title
     * @param array  $parameters
     * @param array  $attributes
     *
     * @return string
     */
    public static function action($action, $title = null, $parameters = [], $attributes = [])
    {
        return static::link(Url::action($action, $parameters), $title, $attributes);
    }

    /**
     * Generate an HTML link to a different language.
     *
     * @param string $language
     * @param string $title
     * @param array  $attributes
     *
     * @return string
     */
    public static function link_to_language($language, $title = null, $attributes = [])
    {
        return static::link(Url::to_language($language), $title, $attributes);
    }

    /**
     * Generate an HTML mailto link.
     *
     * The E-Mail address will be obfuscated to protect it from spam bots.
     *
     * @param string $email
     * @param string $title
     * @param array  $attributes
     *
     * @return string
     */
    public static function mailto($email, $title = null, $attributes = [])
    {
        $email = static::email($email);

        if (null === $title) {
            $title = $email;
        }

        $email = '&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email;

        return '<a href="' . $email . '"' . static::attributes($attributes) . '>' . static::entities($title) . '</a>';
    }

    /**
     * Obfuscate an e-mail address to prevent spam-bots from sniffing it.
     *
     * @param string $email
     *
     * @return string
     */
    public static function email($email)
    {
        return str_replace('@', '&#64;', static::obfuscate($email));
    }

    /**
     * Obfuscate a string to prevent spam-bots from sniffing it.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function obfuscate($value)
    {
        $safe = '';

        foreach (mb_str_split($value) as $letter) {
            if (\ord($letter) > 128) {
                return $letter;
            }

            // To properly obfuscate the value, we will randomly convert each
            // letter to its entity or hexadecimal representation, keeping a
            // bot from sniffing the randomly obfuscated letters.
            switch (mt_rand(1, 3)) {
                case 1:
                    $safe .= '&#' . \ord($letter) . ';';

                    break;

                case 2:
                    $safe .= '&#x' . dechex(\ord($letter)) . ';';

                    break;

                case 3:
                    $safe .= $letter;
            }
        }

        return $safe;
    }

    /**
     * Generate a meta tag.
     *
     * @param string $name
     * @param string $content
     * @param array  $attributes
     *
     * @return string
     */
    public static function meta($name, $content, $attributes = [])
    {
        $defaults = compact('name', 'content');

        $attributes = array_merge($defaults, $attributes);

        return '<meta' . static::attributes($attributes) . '>';
    }

    /**
     * Generates non-breaking space entities based on number supplied.
     *
     * @param int $num
     *
     * @return string
     */
    public static function nbsp($num = 1)
    {
        return str_repeat('&nbsp;', $num);
    }

    /**
     * Generate an html tag.
     *
     * @param string $tag
     * @param array  $attributes
     *
     * @return string
     */
    public static function tag($tag, $content, $attributes = [])
    {
        $content = \is_array($content) ? implode('', $content) : $content;

        return '<' . $tag . static::attributes($attributes) . '>' . $content . '</' . $tag . '>';
    }

    /**
     * Generate an HTML image element.
     *
     * @param string $url
     * @param string $alt
     * @param array  $attributes
     * @param null   $secure
     *
     * @return string
     */
    public static function image($url, $alt = '', $attributes = [], $secure = null)
    {
        $attributes['alt'] = $alt;

        return '<img src="' . Url::asset($url, $secure) . '"' . static::attributes($attributes) . '>';
    }

    /**
     * Generate an ordered list of items.
     *
     * @param array $list
     * @param array $attributes
     *
     * @return string
     */
    public static function ol($list, $attributes = [])
    {
        return static::listing('ol', $list, $attributes);
    }

    /**
     * Generate an ordered or un-ordered list.
     *
     * @param string $type
     * @param array  $list
     * @param array  $attributes
     *
     * @return string
     */
    private static function listing($type, $list, $attributes = [])
    {
        $html = '';

        if (0 === \count($list)) {
            return $html;
        }

        foreach ($list as $key => $value) {
            // If the value is an array, we will recurse the function so that we can
            // produce a nested list within the list being built. Of course, nested
            // lists may exist within nested lists, etc.
            if (\is_array($value)) {
                if (\is_int($key)) {
                    $html .= static::listing($type, $value);
                } else {
                    $html .= '<li>' . $key . static::listing($type, $value) . '</li>';
                }
            } else {
                $html .= '<li>' . static::entities($value) . '</li>';
            }
        }

        return '<' . $type . static::attributes($attributes) . '>' . $html . '</' . $type . '>';
    }

    /**
     * Generate an un-ordered list of items.
     *
     * @param array $list
     * @param array $attributes
     *
     * @return string
     */
    public static function ul($list, $attributes = [])
    {
        return static::listing('ul', $list, $attributes);
    }

    /**
     * Generate a definition list.
     *
     * @param array $list
     * @param array $attributes
     *
     * @return string
     */
    public static function dl($list, $attributes = [])
    {
        $html = '';

        if (0 === \count($list)) {
            return $html;
        }

        foreach ($list as $term => $description) {
            $html .= '<dt>' . static::entities($term) . '</dt>';
            $html .= '<dd>' . static::entities($description) . '</dd>';
        }

        return '<dl' . static::attributes($attributes) . '>' . $html . '</dl>';
    }
}
