<?php

namespace Aurora\Session\Drivers;

use Aurora\Traits\SerializerTrait;

class File extends Driver implements Sweeper
{
    use SerializerTrait;

    public const SESSION_PREFIX = 's_';
    public const SESSION_EXT = '.sess';

    /**
     * The path to which the session files should be written.
     *
     * @var string
     */
    private $path;

    /**
     * Create a new File session driver instance.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Load a session from storage by a given ID.
     *
     * If no session is found for the ID, null will be returned.
     *
     * @param string $id
     *
     * @return array
     */
    public function load($id)
    {
        if (file_exists($path = $this->path($id))) {
            return $this->unserialize(file_get_contents($path));
        }
    }

    /**
     * Save a given session to storage.
     *
     * @param array $session
     * @param array $config
     * @param bool  $exists
     */
    public function save($session, $config, $exists): void
    {
        file_put_contents($this->path($session['id']), $this->serialize($session), \LOCK_EX);
    }

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    public function delete($id): void
    {
        if (file_exists($this->path($id))) {
            @unlink($this->path($id));
        }
    }

    /**
     * Delete all expired sessions from persistent storage.
     *
     * @param int $expiration
     */
    public function sweep($expiration): void
    {
        $files = glob($this->path . '*');

        if (false === $files) {
            return;
        }

        foreach ($files as $file) {
            if ('file' === filetype($file) && filemtime($file) < $expiration) {
                @unlink($file);
            }
        }
    }

    /**
     * Get file path from the cache folder.
     *
     * @param string $key
     *
     * @return string
     */
    private function path($key)
    {
        return $this->path . static::SESSION_PREFIX . $key . static::SESSION_EXT;
    }
}
