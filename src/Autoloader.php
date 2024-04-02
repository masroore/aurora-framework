<?php

namespace Aurora;

class Autoloader
{
    /**
     * The mappings from class names to file paths.
     */
    public static array $mappings = [];

    /**
     * The directories that use the PSR-0 naming convention.
     */
    public static array $directories = [];

    /**
     * The mappings for namespaces to directories.
     */
    public static array $namespaces = [];

    /**
     * The mappings for underscored libraries to directories.
     */
    public static array $underscored = [];

    /**
     * All of the class aliases registered with the auto-loader.
     */
    public static array $aliases = [];

    /**
     * Load the file corresponding to a given class.
     *
     * This method is registered in the bootstrap file as an SPL auto-loader.
     */
    public static function load(string $class)
    {
        // First, we will check to see if the class has been aliased. If it has,
        // we will register the alias, which may cause the auto-loader to be
        // called again for the "real" class name to load its file.
        if (isset(static::$aliases[$class])) {
            return class_alias(static::$aliases[$class], $class);
        }

        // All classes in Aurora are statically mapped. There is no crazy search
        // routine that digs through directories. It's just a simple array of
        // class to file path maps for ultra-fast file loading.
        if (isset(static::$mappings[$class])) {
            require static::$mappings[$class];

            return;
        }

        // If the class namespace is mapped to a directory, we will load the
        // class using the PSR-0 standards from that directory accounting
        // for the root of the namespace by trimming it off.
        foreach (static::$namespaces as $namespace => $directory) {
            if (Str::startsWith($class, $namespace)) {
                return static::load_namespaced($class, $namespace, $directory);
            }
        }

        static::load_psr($class);
    }

    /**
     * Register an array of class to path mappings.
     */
    public static function map(array $mappings): void
    {
        static::$mappings = array_merge(static::$mappings, $mappings);
    }

    /**
     * Register a class alias with the auto-loader.
     */
    public static function alias(string $class, string $alias): void
    {
        static::$aliases[$alias] = $class;
    }

    /**
     * Register directories to be searched as a PSR-0 library.
     */
    public static function directories(array|string $directory): void
    {
        $directories = static::format($directory);

        static::$directories = array_unique(array_merge(static::$directories, $directories));
    }

    /**
     * Register underscored "namespaces" to directory mappings.
     */
    public static function underscored(array $mappings): void
    {
        static::namespaces($mappings, '_');
    }

    /**
     * Map namespaces to directories.
     */
    public static function namespaces(array $mappings, string $append = '\\'): void
    {
        $mappings = static::format_mappings($mappings, $append);

        static::$namespaces = array_merge($mappings, static::$namespaces);
    }

    /**
     * Load a namespaced class from a given directory.
     */
    protected static function load_namespaced(string $class, string $namespace, string $directory)
    {
        return static::load_psr(mb_substr($class, mb_strlen($namespace)), $directory);
    }

    /**
     * Attempt to resolve a class using the PSR-0 standard.
     */
    protected static function load_psr(string $class, ?string $directory = null)
    {
        // The PSR-0 standard indicates that class namespaces and underscores
        // should be used to indicate the directory tree in which the class
        // resides, so we'll convert them to slashes.
        $file = str_replace(['\\', '_'], '/', $class);

        $directories = $directory ?: static::$directories;

        $lower = mb_strtolower($file);

        // Once we have formatted the class name, we'll simply spin through
        // the registered PSR-0 directories and attempt to locate and load
        // the class file into the script.
        foreach ((array)$directories as $dir) {
            if (file_exists($path = $dir . $lower . EXT)) {
                return require $path;
            }

            if (file_exists($path = $dir . $file . EXT)) {
                return require $path;
            }
        }
    }

    /**
     * Format an array of directories with the proper trailing slashes.
     */
    protected static function format(array $directories): array
    {
        return array_map(static fn ($directory) => rtrim($directory, DS) . DS, (array)$directories);
    }

    /**
     * Format an array of namespace to directory mappings.
     */
    protected static function format_mappings(array $mappings, string $append): array
    {
        $namespaces = [];
        foreach ($mappings as $namespace => $directory) {
            // When adding new namespaces to the mappings, we will unset the previously
            // mapped value if it existed. This allows previously registered spaces to
            // be mapped to new directories on the fly.
            $namespace = trim($namespace, $append) . $append;

            unset(static::$namespaces[$namespace]);

            $namespaces[$namespace] = head(static::format($directory));
        }

        return $namespaces;
    }
}
