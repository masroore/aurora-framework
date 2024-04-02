<?php

use Aurora\Arr;
use Aurora\Auth;
use Aurora\Collection;
use Aurora\HigherOrderTapProxy;
use Aurora\HTML;
use Aurora\Input;
use Aurora\Lang;
use Aurora\Optional;
use Aurora\Options;
use Aurora\Redirect;
use Aurora\Session;
use Aurora\Str;
use Aurora\Url;
use Aurora\View;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Convert HTML characters to entities.
 *
 * The encoding specified in the application configuration file will be used.
 */
function e(?string $value): ?string
{
    return HTML::entities($value);
}

/**
 * Retrieve a language line.
 */
function __(string $key, array $replacements = [], ?string $language = null): string
{
    return Lang::line($key, $replacements, $language);
}

/**
 * Get an item from an array or object using "dot" notation.
 *
 * @param mixed|null $default
 */
function data_get(string $target, string $key, $default = null)
{
    if (null === $key) {
        return $target;
    }

    foreach (explode('.', $key) as $segment) {
        if (is_array($target)) {
            if (!array_key_exists($segment, $target)) {
                return value($default);
            }

            $target = $target[$segment];
        } elseif (is_object($target)) {
            if (!isset($target->{$segment})) {
                return value($default);
            }

            $target = $target->{$segment};
        } else {
            return value($default);
        }
    }

    return $target;
}

/**
 * Fill in data where it's missing.
 *
 * @param array|string $key
 */
function data_fill(&$target, $key, $value)
{
    return data_set($target, $key, $value, false);
}

/**
 * Set an item on an array or object using dot notation.
 *
 * @param array|string $key
 * @param bool         $overwrite
 */
function data_set(&$target, $key, $value, $overwrite = true)
{
    $segments = is_array($key) ? $key : explode('.', $key);

    if ('*' === ($segment = array_shift($segments))) {
        if (!Arr::accessible($target)) {
            $target = [];
        }

        if ($segments) {
            foreach ($target as &$inner) {
                data_set($inner, $segments, $value, $overwrite);
            }
        } elseif ($overwrite) {
            foreach ($target as &$inner) {
                $inner = $value;
            }
        }
    } elseif (Arr::accessible($target)) {
        if ($segments) {
            if (!Arr::exists($target, $segment)) {
                $target[$segment] = [];
            }

            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite || !Arr::exists($target, $segment)) {
            $target[$segment] = $value;
        }
    } elseif (is_object($target)) {
        if ($segments) {
            if (!isset($target->{$segment})) {
                $target->{$segment} = [];
            }

            data_set($target->{$segment}, $segments, $value, $overwrite);
        } elseif ($overwrite || !isset($target->{$segment})) {
            $target->{$segment} = $value;
        }
    } else {
        $target = [];

        if ($segments) {
            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }

    return $target;
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param array $vars
     */
    function dd(...$vars): void
    {
        http_response_code(500);

        foreach ($vars as $v) {
            VarDumper::dump($v);
        }

        exit(1);
    }
}

/**
 * Add an element to an array using "dot" notation if it doesn't exist.
 *
 * @param array  $array
 * @param string $key
 *
 * @return array
 */
function array_add($array, $key, $value)
{
    return Arr::add($array, $key, $value);
}

/**
 * Build a new array using a callback.
 *
 * @param array $array
 *
 * @return array
 */
function array_build($array, Closure $callback)
{
    return Arr::build($array, $callback);
}

/**
 * Divide an array into two arrays. One with keys and the other with values.
 *
 * @param array $array
 *
 * @return array
 */
function array_divide($array)
{
    return Arr::divide($array);
}

/**
 * Flatten a multi-dimensional associative array with dots.
 *
 * @param array  $array
 * @param string $prepend
 *
 * @return array
 */
function array_dot($array, $prepend = '')
{
    return Arr::dot($array, $prepend);
}

/**
 * Get all of the given array except for a specified array of items.
 *
 * @param array        $array
 * @param array|string $keys
 *
 * @return array
 */
function array_except($array, $keys)
{
    return Arr::except($array, $keys);
}

/**
 * Fetch a flattened array of a nested array element.
 *
 * @param array  $array
 * @param string $key
 *
 * @return array
 */
function array_fetch($array, $key)
{
    return Arr::fetch($array, $key);
}

/**
 * Return the first element in an array passing a given truth test.
 *
 * @param array      $array
 * @param Closure    $callback
 * @param mixed|null $default
 */
function array_first($array, $callback, $default = null)
{
    return Arr::first($array, $callback, $default);
}

/**
 * Get an item from an array if the specified key exists.
 *
 * @param array      $array
 * @param string     $key
 * @param mixed|null $default
 */
function array_if($array, $key, $default = null)
{
    return is_array($array) && isset($key, $array[$key]) ? $array[$key] : $default;
}

/**
 * Return the last element in an array passing a given truth test.
 *
 * @param array      $array
 * @param Closure    $callback
 * @param mixed|null $default
 */
function array_last($array, $callback, $default = null)
{
    return Arr::last($array, $callback, $default);
}

/**
 * Flatten a multi-dimensional array into a single level.
 *
 * @param array $array
 *
 * @return array
 */
function array_flatten($array)
{
    return Arr::flatten($array);
}

/**
 * Remove one or many array items from a given array using "dot" notation.
 *
 * @param array        $array
 * @param array|string $keys
 */
function array_forget(&$array, $keys): void
{
    Arr::forget($array, $keys);
}

/**
 * Get an item from an array using "dot" notation.
 *
 * @param array      $array
 * @param string     $key
 * @param mixed|null $default
 */
function array_get($array, $key, $default = null)
{
    return Arr::get($array, $key, $default);
}

/**
 * Check if an item exists in an array using "dot" notation.
 *
 * @param array  $array
 * @param string $key
 *
 * @return bool
 */
function array_has($array, $key)
{
    return Arr::has($array, $key);
}

/**
 * Get a subset of the items from the given array.
 *
 * @param array        $array
 * @param array|string $keys
 *
 * @return array
 */
function array_only($array, $keys)
{
    return Arr::only($array, $keys);
}

/**
 * Pluck an array of values from an array.
 *
 * @param array  $array
 * @param string $value
 * @param string $key
 *
 * @return array
 */
function array_pluck($array, $value, $key = null)
{
    return Arr::pluck($array, $value, $key);
}

/**
 * Get a value from the array, and remove it.
 *
 * @param array      $array
 * @param string     $key
 * @param mixed|null $default
 */
function array_pull(&$array, $key, $default = null)
{
    return Arr::pull($array, $key, $default);
}

/**
 * Set an array item to a given value using "dot" notation.
 *
 * If no key is given to the method, the entire array will be replaced.
 *
 * @param array  $array
 * @param string $key
 *
 * @return array
 */
function array_set(&$array, $key, $value)
{
    return Arr::set($array, $key, $value);
}

/**
 * Sort the array using the given Closure.
 */
function array_sort(array $array, Closure $callback): array
{
    return Arr::sort($array, $callback);
}

/**
 * Filter the array using the given Closure.
 */
function array_where(array $array, Closure $callback): array
{
    return Arr::where($array, $callback);
}

/**
 * Transform Eloquent models to a JSON object.
 */
function eloquent_to_json(array|Model $models): object|string
{
    if ($models instanceof Aurora\Database\Eloquent\Model) {
        return json_encode($models->toArray());
    }

    return json_encode(array_map(static fn ($m) => $m->toArray(), $models));
}

/**
 * Return the first element of an array.
 *
 * This is simply a convenient wrapper around the "reset" method.
 */
function head(array $array): mixed
{
    return reset($array);
}

/**
 * Get the last element from an array.
 */
function last(array $array): mixed
{
    return end($array);
}

/**
 * Generate an application URL.
 *
 * <code>
 *        // Create a URL to a location within the application
 *        $url = url('user/profile');
 *
 *        // Create a HTTPS URL to a location within the application
 *        $url = url('user/profile', true);
 * </code>
 */
function url(string $url = '', ?bool $https = null): string
{
    return Url::to($url, $https);
}

/**
 * Generate an application URL to an asset.
 */
function asset(string $url, ?bool $https = null): string
{
    return Url::asset($url, $https);
}

/**
 * Generate a URL to a controller action.
 *
 * <code>
 *        // Generate a URL to the "index" method of the "user" controller
 *        $url = action('user@index');
 *
 *        // Generate a URL to http://example.com/user/profile/taylor
 *        $url = action('user@profile', array('taylor'));
 * </code>
 *
 * @param string $action
 * @param array  $parameters
 *
 * @return string
 */
function action($action, $parameters = [])
{
    return Url::action($action, $parameters);
}

/**
 * Generate a URL from a route name.
 *
 * <code>
 *        // Create a URL to the "profile" named route
 *        $url = route('profile');
 *
 *        // Create a URL to the "profile" named route with wildcard parameters
 *        $url = route('profile', array($username));
 * </code>
 */
function route(string $name, array $parameters = []): string
{
    return Url::route($name, $parameters);
}

/**
 * Get the path to the storage folder.
 */
function storage_path(string $path = ''): string
{
    return STORAGE_PATH . ($path ? '/' . $path : $path);
}

/**
 * Get the root namespace of a given class.
 */
function root_namespace(string $class, string $separator = '\\'): string
{
    if (str_contains($class, $separator)) {
        return head(explode($separator, $class));
    }
}

/**
 * Get the "class basename" of a class or object.
 *
 * The basename is considered to be the name of the class minus all namespaces.
 *
 * @param object|string $class
 */
function class_basename($class): string
{
    if (is_object($class)) {
        $class = $class::class;
    }

    return basename(str_replace('\\', '/', $class));
}

/**
 * Return the value of the given item.
 *
 * If the given item is a Closure the result of the Closure will be returned.
 */
function value($value)
{
    return $value instanceof Closure ? $value() : $value;
}

/**
 * Return the given object. Useful for chaining.
 */
function with($object)
{
    return $object;
}

/**
 * Determine if the current version of PHP is at least the supplied version.
 *
 * @param string $version
 *
 * @return bool
 */
function has_php($version)
{
    return version_compare(\PHP_VERSION, $version) >= 0;
}

/**
 * Get a view instance.
 */
function view(string $view, array $data = []): View
{
    return View::make($view, $data);
}

/**
 * Render the given view.
 */
function render(string $view, array $data = []): string
{
    return View::make($view, $data)->render();
}

/**
 * Get the rendered contents of a partial from a loop.
 */
function render_each(string $partial, array $data, string $iterator, string $empty = 'raw|'): string
{
    return View::render_each($partial, $data, $iterator, $empty);
}

/**
 * Get the string contents of a section.
 */
function yield_content(string $section): string
{
    return Aurora\Section::yield_content($section);
}

/**
 * Determine if a value is "filled".
 */
function filled($value): bool
{
    return !blank($value);
}

/**
 * Get a CLI option from the argv $_SERVER variable.
 */
function get_cli_option(string $option, mixed $default = null): ?string
{
    foreach (Aurora\Request::foundation()->server->get('argv') as $argument) {
        if (Str::startsWith($argument, "--{$option}=")) {
            return mb_substr($argument, mb_strlen($option) + 3);
        }
    }

    return value($default);
}

/**
 * Calculate the human-readable file size (with proper units).
 */
function get_file_size(int $size): string
{
    $units = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

    return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $units[$i];
}

function _get_env(string $key)
{
    if (array_has($_SERVER, $key)) {
        return $_SERVER[$key];
    }

    return null;
}

/**
 * Gets the value of an environment variable.
 */
function env(string $key, mixed $default = null)
{
    // $value = getenv($key);
    $value = _get_env($key);

    if (null === $value) {
        return value($default);
    }

    switch (mb_strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    if (mb_strlen($value) > 1 && Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
        return mb_substr($value, 1, -1);
    }

    return $value;
}

/**
 * Determine if the given value is "blank".
 */
function blank($value): bool
{
    if (null === $value) {
        return true;
    }

    if (is_string($value)) {
        return '' === trim($value);
    }

    if (is_numeric($value) || is_bool($value)) {
        return false;
    }

    if ($value instanceof Countable) {
        return 0 === count($value);
    }

    return empty($value);
}

function style(string $url, array $attributes = []): string
{
    return HTML::style($url, $attributes);
}

function script(string $url, array $attributes = []): string
{
    return HTML::script($url, $attributes);
}

function config($key, $default = null)
{
    return Aurora\Config::get($key, $default);
}

function app_env()
{
    return env('APP_ENV', 'production');
}

function env_is_local(): bool
{
    return 'local' === app_env();
}

function env_is_production(): bool
{
    return 'production' === app_env();
}

/**
 * Generate a CSRF token form field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

/**
 * Get the CSRF token value.
 */
function csrf_token(): string
{
    return Session::token();
}

/**
 * Get an item from an object using "dot" notation.
 *
 * @param object     $object
 * @param string     $key
 * @param mixed|null $default
 */
function object_get($object, $key, $default = null)
{
    if (blank($key)) {
        return $object;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_object($object) || !isset($object->{$segment})) {
            return value($default);
        }

        $object = $object->{$segment};
    }

    return $object;
}

function old(?string $key = null, $default = null): ?string
{
    return Input::old($key, $default);
}

/**
 * Provide access to optional objects.
 *
 * @param mixed|null $value
 * @param ?callable  $callback
 */
function optional($value = null, ?callable $callback = null)
{
    if (null === $callback) {
        return new Optional($value);
    }

    if (null !== $value) {
        return $callback($value);
    }
}

/**
 * Replace a given pattern with each value in the array in sequentially.
 */
function preg_replace_array(string $pattern, array $replacements, string $subject): string
{
    return preg_replace_callback(
        $pattern,
        static function () use (&$replacements) {
            foreach ($replacements as $key => $value) {
                return array_shift($replacements);
            }
        },
        $subject
    );
}

/**
 * Replace a given pattern with each value in the array in sequentially.
 *
 * @param array $replacements
 */
function preg_replace_sub(string $pattern, &$replacements, string $subject): string
{
    return preg_replace_callback($pattern, static function ($match) use (&$replacements) {
        return array_shift($replacements);
    }, $subject);
}

/**
 * Get the available auth instance.
 *
 * @return mixed|null
 */
function auth()
{
    return Auth::user();
}

/**
 * Determine if the user of the application is not logged in.
 */
function guest(): bool
{
    return Auth::guest();
}

/**
 * Get an instance of the redirector.
 *
 * @param ?string $to
 */
function redirect(?string $to = null, int $status = 302, ?bool $secure = null): Redirect
{
    if (null === $to) {
        return Redirect::home();
    }

    return Redirect::to($to, $status, $secure);
}

/**
 * Retry an operation a given number of times.
 *
 * @throws Exception
 */
function retry(int $times, callable $callback, int $sleep = 0)
{
    --$times;

    beginning:
    try {
        return $callback();
    } catch (Exception $e) {
        if (!$times) {
            throw $e;
        }

        --$times;

        if ($sleep) {
            usleep($sleep * 1000);
        }

        goto beginning;
    }
}

/**
 * Create a redirect response to the HTTP referrer.
 */
function back(int $status = 302): Redirect
{
    return Redirect::back($status);
}

/**
 * Create a collection from the given value.
 */
function collect(mixed $value = null): Collection
{
    return new Collection($value);
}

/*
 * Determine if a given string contains a given substring.
 *
 * @param string $haystack
 * @param array|string $needles
 *
 * @return bool
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, $needles): bool
    {
        return Str::contains($haystack, $needles);
    }
}

/**
 * Cap a string with a single instance of a given value.
 */
function str_finish(string $value, string $cap): string
{
    return Str::finish($value, $cap);
}

/**
 * Determine if a given string matches a given pattern.
 */
function str_is(array|string $pattern, string $value): bool
{
    return Str::is($pattern, $value);
}

/**
 * Limit the number of characters in a string.
 */
function str_limit(string $value, int $limit = 100, string $end = '...'): string
{
    return Str::limit($value, $limit, $end);
}

/**
 * Determine if the given object has a toString method.
 */
function str_object(object $value): bool
{
    return is_object($value) && method_exists($value, '__toString');
}

/**
 * Get the plural form of an English word.
 */
function str_plural(string $value, int $count = 2): string
{
    return Str::plural($value, $count);
}

/**
 * Generate a more truly "random" alpha-numeric string.
 *
 * @throws Exception
 */
function str_random(int $length = 16): string
{
    return Str::random($length);
}

/**
 * Replace a given value in the string sequentially with an array.
 */
function str_replace_array(string $search, array $replace, string $subject): string
{
    return Str::replaceArray($search, $replace, $subject);
}

/**
 * Replace the first occurrence of a given value in the string.
 */
function str_replace_first(string $search, string $replace, string $subject): string
{
    return Str::replaceFirst($search, $replace, $subject);
}

/**
 * Replace the last occurrence of a given value in the string.
 *
 * @return string
 */
function str_replace_last(string $search, string $replace, string $subject)
{
    return Str::replaceLast($search, $replace, $subject);
}

/**
 * Get the singular form of an English word.
 */
function str_singular(string $value): string
{
    return Str::singular($value);
}

/**
 * Generate a URL friendly "slug" from a given string.
 */
function str_slug(string $title, string $separator = '-', string $language = 'en'): string
{
    return Str::slug($title, $separator, $language);
}

/**
 * Begin a string with a single instance of a given value.
 */
function str_start(string $value, string $prefix): string
{
    return Str::start($value, $prefix);
}

/**
 * Convert a value to studly caps case.
 */
function studly_case(string $value): string
{
    return Str::studly($value);
}

/**
 * Call the given Closure with the given value then return the value.
 */
function tap($value, ?callable $callback = null)
{
    if (null === $callback) {
        return new HigherOrderTapProxy($value);
    }

    $callback($value);

    return $value;
}

/**
 * Throw the given exception if the given condition is true.
 *
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable|string $exception, array ...$parameters): bool
{
    if ($condition) {
        throw is_string($exception) ? new $exception(...$parameters) : $exception;
    }

    return $condition;
}

/**
 * Throw the given exception unless the given condition is true.
 *
 * @throws Throwable
 */
function throw_unless(bool $condition, Throwable|string $exception, array ...$parameters): bool
{
    if (!$condition) {
        throw is_string($exception) ? new $exception(...$parameters) : $exception;
    }

    return $condition;
}

/**
 * Transform the given value if it is present.
 */
function transform($value, callable $callback, mixed $default = null): mixed
{
    if (filled($value)) {
        return $callback($value);
    }

    if (is_callable($default)) {
        return $default($value);
    }

    return $default;
}

/**
 * Converts params into string.
 */
function param_str($params): string
{
    $toStr = static function ($value) {
        if (!is_scalar($value)) {
            return gettype($value);
        }

        if ('' === $value) {
            return "''";
        }

        if (strlen("$value") > 20) {
            return substr("$value", 0, 15) . '...';
        }

        return $value;
    };

    return implode(' | ', array_map($toStr, $params));
}

/**
 * Get / set the specified option value.
 *
 * If an array is passed as the key, we will assume you want to set an array of values.
 *
 * @return mixed|Options
 */
function options(array|string|null $key = null, mixed $default = null)
{
    $options = Options::make(storage_path(env('OPTIONS_STORE', 'options.json')));

    if (null === $key) {
        return $options;
    }

    if (is_array($key)) {
        return $options->put($key);
    }

    return $options->get($key, $default);
}

function array_strip_slashes(array $array): array
{
    $result = [];

    foreach ($array as $key => $value) {
        $key = stripslashes($key);

        // If the value is an array, we will just recurse back into the
        // function to keep stripping the slashes out of the array,
        // otherwise we will set the stripped value.
        if (is_array($value)) {
            $result[$key] = array_strip_slashes($value);
        } else {
            $result[$key] = stripslashes($value);
        }
    }

    return $result;
}
