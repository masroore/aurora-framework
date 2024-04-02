<?php

namespace Aurora;

class Section
{
    /**
     * All of the captured sections.
     */
    public static array $sections = [];

    /**
     * The last section on which injection was started.
     */
    public static array $last = [];

    /**
     * Inject inline content into a section.
     *
     * This is helpful for injecting simple strings such as page titles.
     *
     * <code>
     *        // Inject inline content into the "header" section
     *        Section::inject('header', '<title>Aurora</title>');
     * </code>
     */
    public static function inject(string $section, string $content): void
    {
        static::start($section, $content);
    }

    /**
     * Start injecting content into a section.
     *
     * <code>
     *        // Start injecting into the "header" section
     *        Section::start('header');
     *
     *        // Inject a raw string into the "header" section without buffering
     *        Section::start('header', '<title>Aurora</title>');
     * </code>
     */
    public static function start(string $section, \Closure|string $content = ''): void
    {
        if ('' === $content) {
            ob_start() && static::$last[] = $section;
        } else {
            static::extend($section, $content);
        }
    }

    /**
     * Extend the content in a given section.
     */
    protected static function extend(string $section, string $content): void
    {
        if (isset(static::$sections[$section])) {
            static::$sections[$section] = str_replace('@parent', $content, static::$sections[$section]);
        } else {
            static::$sections[$section] = $content;
        }
    }

    /**
     * Stop injecting content into a section and return its contents.
     */
    public static function yield_section(): string
    {
        return static::yield_content(static::stop());
    }

    /**
     * Get the string contents of a section.
     */
    public static function yield_content(string $section): string
    {
        return (isset(static::$sections[$section])) ? static::$sections[$section] : '';
    }

    /**
     * Stop injecting content into a section.
     */
    public static function stop(): string
    {
        static::extend($last = array_pop(static::$last), ob_get_clean());

        return $last;
    }

    /**
     * Append content to a given section.
     */
    public static function append(string $section, string $content): void
    {
        if (isset(static::$sections[$section])) {
            static::$sections[$section] .= $content;
        } else {
            static::$sections[$section] = $content;
        }
    }
}
