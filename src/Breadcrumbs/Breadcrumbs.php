<?php

namespace Aurora\Breadcrumbs;

class Breadcrumbs
{
    private static $callbacks = [];

    private static $view = 'vendor.breadcrumbs.default';

    public static function register($name, callable $callback): void
    {
        self::$callbacks[$name] = $callback;
    }

    /**
     * @return string
     */
    public static function getView()
    {
        return self::$view;
    }

    /**
     * @param string $view
     */
    public static function setView($view): void
    {
        self::$view = $view;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public static function generate($name, array $args = [])
    {
        if (!\is_array($args)) {
            $args = \array_slice(\func_get_args(), 1);
        }

        $generator = new BreadcrumbsGenerator(self::$callbacks);
        $generator->call($name, $args);

        return $generator->toArray();
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return string
     */
    public static function render($name, $args = [])
    {
        if (!\is_array($args)) {
            $args = \array_slice(\func_get_args(), 1);
        }

        $breadcrumbs = self::generate($name, $args);

        return view(self::$view, compact('breadcrumbs'))->render();
    }
}
