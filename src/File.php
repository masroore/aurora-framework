<?php

namespace Aurora;

use FilesystemIterator as fIterator;

class File
{
    /**
     * Write to a file.
     */
    public static function put(string $path, string $data): int
    {
        return file_put_contents($path, $data, \LOCK_EX);
    }

    /**
     * Append to a file.
     */
    public static function append(string $path, string $data): int
    {
        return file_put_contents($path, $data, \LOCK_EX | \FILE_APPEND);
    }

    /**
     * Delete a file.
     */
    public static function delete(string $path): bool
    {
        if (static::exists($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * Determine if a file exists.
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Move a file to a new location.
     */
    public static function move(string $path, string $target): void
    {
        rename($path, $target);
    }

    /**
     * Copy a file to a new location.
     */
    public static function copy(string $path, string $target): void
    {
        copy($path, $target);
    }

    /**
     * Extract the file extension from a file path.
     */
    public static function extension(string $path): string
    {
        return pathinfo($path, \PATHINFO_EXTENSION);
    }

    /**
     * Get the file type of a given file.
     */
    public static function type(string $path): string
    {
        return filetype($path);
    }

    /**
     * Get the file size of a given file.
     */
    public static function size(string $path): int
    {
        return filesize($path);
    }

    /**
     * Get the file's last modification time.
     */
    public static function modified(string $path): int
    {
        return filemtime($path);
    }

    /**
     * Get a file MIME type by extension.
     *
     * <code>
     *        // Determine the MIME type for the .tar extension
     *        $mime = File::mime('tar');
     *
     *        // Return a default value if the MIME can't be determined
     *        $mime = File::mime('ext', 'application/octet-stream');
     * </code>
     */
    public static function mime(string $extension, string $default = 'application/octet-stream'): string
    {
        $mimes = Config::get('mimes');

        if (!\array_key_exists($extension, $mimes)) {
            return $default;
        }

        return \is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
    }

    /**
     * Get the contents of a file.
     *
     * <code>
     *        // Get the contents of a file
     *        $contents = File::get(APP_PATH.'routes'.EXT);
     *
     *        // Get the contents of a file or return a default value if it doesn't exist
     *        $contents = File::get(APP_PATH.'routes'.EXT, 'Default Value');
     * </code>
     */
    public static function get(string $path, mixed $default = null): string
    {
        return file_exists($path) ? file_get_contents($path) : value($default);
    }

    /**
     * Determine if a file is of a given type.
     *
     * The Fileinfo PHP extension is used to determine the file's MIME type.
     *
     * <code>
     *        // Determine if a file is a JPG image
     *        $jpg = File::is('jpg', 'path/to/file.jpg');
     *
     *        // Determine if a file is one of a given list of types
     *        $image = File::is(array('jpg', 'png', 'gif'), 'path/to/file');
     * </code>
     */
    public static function is(array|string $extensions, string $path): bool
    {
        $mimes = Config::get('mimes');

        $mime = finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $path);

        // The MIME configuration file contains an array of file extensions and
        // their associated MIME types. We will loop through each extension the
        // developer wants to check and look for the MIME type.
        foreach ((array)$extensions as $extension) {
            if (isset($mimes[$extension]) && \in_array($mime, (array)$mimes[$extension], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new directory.
     */
    public static function mkdir(string $path, int $chmod = 0o777): bool
    {
        return is_dir($path) || mkdir($path, $chmod, true);
    }

    /**
     * Move a directory from one location to another.
     */
    public static function mvdir(string $source, string $destination, int $options = fIterator::SKIP_DOTS): bool
    {
        return static::cpdir($source, $destination, true, $options);
    }

    /**
     * Recursively copy directory contents to another directory.
     */
    public static function cpdir(string $source, string $destination, bool $delete = false, int $options = fIterator::SKIP_DOTS): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        // First we need to create the destination directory if it doesn't
        // already exists. This directory hosts all of the assets we copy
        // from the installed bundle's source directory.
        if (!is_dir($destination)) {
            mkdir($destination, 0o777, true);
        }

        $items = new fIterator($source, $options);

        foreach ($items as $item) {
            $location = $destination . DS . $item->getBasename();

            // If the file system item is a directory, we will recurse the
            // function, passing in the item directory. To get the proper
            // destination path, we'll add the basename of the source to
            // to the destination directory.
            if ($item->isDir()) {
                $path = $item->getRealPath();

                if (!static::cpdir($path, $location, $delete, $options)) {
                    return false;
                }

                if ($delete) {
                    @rmdir($item->getRealPath());
                }
            }
            // If the file system item is an actual file, we can copy the
            // file from the bundle asset directory to the public asset
            // directory. The "copy" method will overwrite any existing
            // files with the same name.
            else {
                if (!copy($item->getRealPath(), $location)) {
                    return false;
                }

                if ($delete) {
                    @unlink($item->getRealPath());
                }
            }
        }

        unset($items);
        if ($delete) {
            @rmdir($source);
        }

        return true;
    }

    /**
     * Empty the specified directory of all files and folders.
     */
    public static function cleandir(string $directory): void
    {
        self::rmdir($directory, true);
    }

    /**
     * Recursively delete a directory.
     */
    public static function rmdir(string $directory, bool $preserve = false): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new fIterator($directory);

        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the
            // function and delete that sub-directory, otherwise we'll
            // just delete the file and keep going!
            if ($item->isDir()) {
                static::rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        unset($items);
        if (!$preserve) {
            @rmdir($directory);
        }
    }

    /**
     * Get the most recently modified file in a directory.
     */
    public static function latest(string $directory, int $options = fIterator::SKIP_DOTS): ?\SplFileInfo
    {
        $latest = null;

        $time = 0;

        $items = new fIterator($directory, $options);

        // To get the latest created file, we'll simply loop through the
        // directory, setting the latest file if we encounter a file
        // with a UNIX timestamp greater than the latest one.
        foreach ($items as $item) {
            if ($item->getMTime() > $time) {
                $latest = $item;
                $time = $item->getMTime();
            }
        }

        return $latest;
    }

    /**
     * Get the MD5 hash of the file at the given path.
     */
    public function hash(string $path): string
    {
        return md5_file($path);
    }

    /**
     * Determine if a file or directory is missing.
     */
    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }
}
