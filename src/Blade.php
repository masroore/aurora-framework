<?php

namespace Aurora;

class Blade
{
    public const COMPILED_VIEW_FOLDER = ''; // 'views/'
    public const COMPILED_VIEW_EXT = '.vue';
    public const COMPILED_VIEW_PREFIX = 'v_';

    /**
     * All of the compiler functions used by Blade.
     *
     * @var array
     */
    protected static $compilers = [
        'extensions',
        'extends',
        'comments',
        'echos',
        'forelse',
        'empty',
        'endforelse',
        'structure_openings',
        'structure_closings',
        'else',
        'unless',
        'endunless',
        'includes',
        'render_each',
        'render',
        'yields',
        'yield_sections',
        'section_start',
        'section_end',
        'stop',
        'dump',
        'guest',
        'endguest',
        'auth',
        'endauth',
        'csrf',
        'csrf_token',
        'method',
        'style',
        'styles',
        'script',
        'scripts',
    ];

    /**
     * An array of user defined compilers.
     *
     * @var array
     */
    protected static $extensions = [];

    /**
     * Array of opening and closing tags for raw echos.
     *
     * @var array
     */
    protected static $rawTags = ['{!!', '!!}'];

    /**
     * Array of opening and closing tags for regular echos.
     *
     * @var array
     */
    protected static $contentTags = ['{{', '}}'];

    /**
     * Array of opening and closing tags for escaped echos.
     *
     * @var array
     */
    protected static $escapedTags = ['{{{', '}}}'];

    /**
     * The "regular" / legacy echo string format.
     *
     * @var string
     */
    protected static $echoFormat = 'e(%s)';

    /**
     * Register the Blade view engine with Aurora.
     */
    public static function sharpen(): void
    {
        Event::listen(View::engine, static function ($view) {
            // The Blade view engine should only handle the rendering of views which
            // end with the Blade extension. If the given view does not, we will
            // return false so the View can be rendered as normal.
            if (!str_contains($view->path, BLADE_EXT)) {
                return;
            }

            $compiled = self::compiled($view->path);

            // If the view doesn't exist or has been modified since the last time it
            // was compiled, we will recompile the view into pure PHP from it's
            // Blade representation, writing it to cached storage.
            if (!file_exists($compiled) || self::expired($view->view, $view->path)) {
                file_put_contents($compiled, self::compile($view));
            }

            $view->path = $compiled;

            // Once the view has been compiled, we can simply set the path to the
            // compiled view on the view instance and call the typical "get"
            // method on the view to evaluate the compiled PHP view.
            return ltrim($view->get());
        });
    }

    /**
     * Get the fully qualified path for a compiled view.
     *
     * @param string $path
     *
     * @return string
     */
    public static function compiled($path)
    {
        return STORAGE_PATH . static::COMPILED_VIEW_FOLDER . static::COMPILED_VIEW_PREFIX . md5($path) . static::COMPILED_VIEW_EXT;
    }

    /**
     * Determine if a view is "expired" and needs to be re-compiled.
     *
     * @param string $view
     * @param string $path
     *
     * @return bool
     */
    public static function expired($view, $path)
    {
        return filemtime($path) > filemtime(static::compiled($path));
    }

    /**
     * Compiles the specified file containing Blade pseudo-code into valid PHP.
     *
     * @param string $view
     *
     * @return string
     */
    public static function compile($view)
    {
        return static::compile_string(file_get_contents($view->path), $view);
    }

    /**
     * Compiles the given string containing Blade pseudo-code into valid PHP.
     *
     * @param string $value
     * @param View   $view
     *
     * @return string
     */
    public static function compile_string($value, $view = null)
    {
        foreach (static::$compilers as $compiler) {
            $method = "compile_{$compiler}";

            $value = static::$method($value, $view);
        }

        return $value;
    }

    /**
     * Register a custom Blade compiler.
     *
     * <code>
     *        Blade::extend(function($view)
     *        {
     *            return str_replace('foo', 'bar', $view);
     *        });
     * </code>
     */
    public static function extend(\Closure $compiler): void
    {
        static::$extensions[] = $compiler;
    }

    /**
     * Get the regular expression for a generic Blade function.
     *
     * @param string $function
     *
     * @return string
     */
    public static function matcher($function)
    {
        return '/(\s*)@' . $function . '(\s*\(.*\))/';
    }

    /**
     * Compile the default values for the echo statement.
     *
     * @param string $value
     *
     * @return string
     */
    public static function compileEchoDefaults($value)
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/si', 'isset($1) ? $1 : $2', $value);
    }

    /**
     * Rewrites Blade "@layout" expressions into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_extends($value)
    {
        // If the Blade template is not using "layouts", we'll just return it
        // unchanged since there is nothing to do with layouts and we will
        // just let the other Blade compilers handle the rest.
        if (!Str::startsWith($value, '@extends')) {
            return $value;
        }

        // First we'll split out the lines of the template so we can get the
        // layout from the top of the template. By convention it must be
        // located on the first line of the template contents.
        $lines = preg_split("/(\r?\n)/", $value);

        $pattern = static::matcher('extends');

        $lines[] = preg_replace($pattern, '$1@include$2', $lines[0]);

        // We will add a "render" statement to the end of the templates and
        // then slice off the "@layout" shortcut from the start so the
        // sections register before the parent template renders.
        return implode(CRLF, \array_slice($lines, 1));
    }

    /**
     * Extract a variable value out of a Blade expression.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function extract($value, $expression)
    {
        preg_match('/@layout(\s*\(.*\))(\s*)/', $value, $matches);

        return str_replace(["('", "')"], '', $matches[1]);
    }

    /**
     * Rewrites Blade comments into PHP comments.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_comments($value)
    {
        $value = preg_replace('/\{\{--(.+?)(--\}\})?\n/', '<?php // $1 ?>', $value);

        return preg_replace('/\{\{--((.|\s)*?)--\}\}/', "<?php /* $1 */ ?>\n", $value);
    }

    /**
     * Rewrites Blade "for else" statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_forelse($value)
    {
        preg_match_all('/(\s*)@forelse(\s*\(.*\))(\s*)/', $value, $matches);

        foreach ($matches[0] as $forelse) {
            preg_match('/\s*\(\s*(\S*)\s/', $forelse, $variable);

            // Once we have extracted the variable being looped against, we can add
            // an if statement to the start of the loop that checks if the count
            // of the variable being looped against is greater than zero.
            $if = "<?php if (count({$variable[1]}) > 0): ?>";

            $search = '/(\s*)@forelse(\s*\(.*\))/';

            $replace = '$1' . $if . '<?php foreach$2: ?>';

            $blade = preg_replace($search, $replace, $forelse);

            // Finally, once we have the check prepended to the loop we'll replace
            // all instances of this forelse syntax in the view content of the
            // view being compiled to Blade syntax with real PHP syntax.
            $value = str_replace($forelse, $blade, $value);
        }

        return $value;
    }

    /**
     * Rewrites Blade "empty" statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_empty($value)
    {
        return str_replace('@empty', '<?php endforeach; ?><?php else: ?>', $value);
    }

    /**
     * Rewrites Blade "forelse" endings into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_endforelse($value)
    {
        return str_replace('@endforelse', '<?php endif; ?>', $value);
    }

    /**
     * Rewrites Blade structure openings into PHP structure openings.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_structure_openings($value)
    {
        $pattern = '/(\s*)@(if|elseif|foreach|for|while)(\s*\(.*\))/';

        return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
    }

    /**
     * Rewrites Blade structure closings into PHP structure closings.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_structure_closings($value)
    {
        $pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

        return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
    }

    /**
     * Rewrites Blade else statements into PHP else statements.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_else($value)
    {
        return preg_replace('/(\s*)@(else)(\s*)/', '$1<?php $2: ?>$3', $value);
    }

    /**
     * Rewrites Blade "unless" statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_unless($value)
    {
        $pattern = '/(\s*)@unless(\s*\(.*\))/';

        return preg_replace($pattern, '$1<?php if ( ! ($2)): ?>', $value);
    }

    /**
     * Rewrites Blade "unless" endings into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_endunless($value)
    {
        return str_replace('@endunless', '<?php endif; ?>', $value);
    }

    protected static function compile_guest($value)
    {
        return str_replace('@guest', '<?php if(Auth::guest()): ?>', $value);
    }

    protected static function compile_csrf($value)
    {
        return str_replace('@csrf', '<?php echo csrf_field(); ?>', $value);
    }

    protected static function compile_csrf_token($value)
    {
        return str_replace('@csrf_token', '<?php echo csrf_token(); ?>', $value);
    }

    protected static function compile_method($value)
    {
        $pattern = static::matcher('method');

        return preg_replace($pattern, '$1<?php echo method_field($2); ?>', $value);
    }

    protected static function compile_endguest($value)
    {
        return str_replace('@endguest', '<?php endif; ?>', $value);
    }

    protected static function compile_auth($value)
    {
        return str_replace('@auth', '<?php if(Auth::check()): ?>', $value);
    }

    protected static function compile_endauth($value)
    {
        return str_replace('@endauth', '<?php endif; ?>', $value);
    }

    /**
     * Rewrites Blade @include statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_includes($value)
    {
        $pattern = static::matcher('include');

        return preg_replace($pattern, '$1<?php echo view$2->with(get_defined_vars())->render(); ?>', $value);
    }

    /**
     * Rewrites Blade @style statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_style($value)
    {
        $pattern = static::matcher('style');

        return preg_replace($pattern, '$1<?php echo style($2); ?>', $value);
    }

    protected static function compile_styles($value)
    {
        return preg_replace('/@styles/', '<?php echo Asset::styles(); ?>', $value);
    }

    /**
     * Rewrites Blade @script statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_script($value)
    {
        $pattern = static::matcher('script');

        return preg_replace($pattern, '$1<?php echo script($2); ?>', $value);
    }

    protected static function compile_scripts($value)
    {
        return preg_replace('/@scripts/', '<?php echo Asset::scripts(); ?>', $value);
    }

    /**
     * Rewrites Blade @dump statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_dump($value)
    {
        $pattern = static::matcher('dump');

        return preg_replace($pattern, '$1<?php (new Aurora\Debug\Dumper)->dump($2); ?>', $value);
    }

    /**
     * Rewrites Blade @render statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_render($value)
    {
        $pattern = static::matcher('render');

        return preg_replace($pattern, '$1<?php echo render$2; ?>', $value);
    }

    /**
     * Rewrites Blade @render_each statements into valid PHP.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_render_each($value)
    {
        $pattern = static::matcher('render_each');

        return preg_replace($pattern, '$1<?php echo render_each$2; ?>', $value);
    }

    /**
     * Rewrites Blade @yield statements into Section statements.
     *
     * The Blade @yield statement is a shortcut to the Section::yield method.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_yields($value)
    {
        $pattern = static::matcher('yield');

        return preg_replace($pattern, '$1<?php echo \\Aurora\\Section::yield_content$2; ?>', $value);
    }

    /**
     * Rewrites Blade yield section statements into valid PHP.
     *
     * @return string
     */
    protected static function compile_yield_sections($value)
    {
        $replace = '<?php echo \\Aurora\\Section::yield_section(); ?>';

        return str_replace('@yield_section', $replace, $value);
    }

    /**
     * Rewrites Blade @section statements into Section statements.
     *
     * The Blade @section statement is a shortcut to the Section::start method.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_section_start($value)
    {
        $pattern = static::matcher('section');

        return preg_replace($pattern, '$1<?php \\Aurora\\Section::start$2; ?>', $value);
    }

    /**
     * Rewrites Blade @endsection statements into Section statements.
     *
     * The Blade @endsection statement is a shortcut to the Section::stop method.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_section_end($value)
    {
        return preg_replace('/@endsection/', '<?php \\Aurora\\Section::stop(); ?>', $value);
    }

    protected static function compile_stop($value)
    {
        return preg_replace('/@stop/', '<?php \\Aurora\\Section::stop(); ?>', $value);
    }

    /**
     * Execute user defined compilers.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_extensions($value)
    {
        foreach (static::$extensions as $compiler) {
            $value = $compiler($value);
        }

        return $value;
    }

    /**
     * Rewrites Blade echo statements into PHP echo statements.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_echos($value)
    {
        // Get the echo methods in the proper order for compilation.
        $echoMethods = [
            'compile_raw_echos',
            'compile_escaped_echos',
            'compile_regular_echos',
        ];

        foreach ($echoMethods as $method) {
            $value = static::$method($value);
        }

        return $value;
    }

    /**
     * Compile the "raw" echo statements.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_raw_echos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', static::$rawTags[0], static::$rawTags[1]);

        $callback = static function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
            $wrapped = static::compileEchoDefaults($matches[2]);

            return $matches[1] ? mb_substr($matches[0], 1) : "<?php echo {$wrapped}; ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the "regular" echo statements.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_regular_echos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', static::$contentTags[0], static::$contentTags[1]);

        $callback = static function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            $wrapped = sprintf(static::$echoFormat, static::compileEchoDefaults($matches[2]));

            return $matches[1] ? mb_substr($matches[0], 1) : "<?php echo {$wrapped}; ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the escaped echo statements.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function compile_escaped_echos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', static::$escapedTags[0], static::$escapedTags[1]);

        $callback = static function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            $wrapped = static::compileEchoDefaults($matches[2]);

            return $matches[1] ? $matches[0] : "<?php echo e({$wrapped}); ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }
}
