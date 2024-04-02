<?php

namespace Aurora;

/*
 * env.php
 *
 * @author     Dr. Max Ehsan <contact@kaijuscripts.com>
 * @copyright  2018 Dr. Max Ehsan
 */

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

class LoadEnvironmentVariables
{
    public const ENV_FILE = '.env';

    /**
     * The environment file to load during bootstrapping.
     */
    protected string $environmentFile = self::ENV_FILE;

    /**
     * The custom environment path defined by the developer.
     */
    protected ?string $environmentPath = null;

    private ?string $basePath = null;

    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->basePath = rtrim($basePath, '\/');
        }
    }

    public function bootstrap(): void
    {
        try {
            Dotenv::createImmutable($this->environmentPath(), $this->environmentFile())->load();
        } catch (InvalidPathException $e) {
        }
    }

    /**
     * Get the path to the environment file directory.
     */
    public function environmentPath(): string
    {
        return null !== $this->environmentPath ?: $this->basePath;
    }

    /**
     * Get the environment file the application is using.
     */
    public function environmentFile(): string
    {
        return $this->environmentFile ?: self::ENV_FILE;
    }

    /**
     * Get the fully qualified path to the environment file.
     */
    public function environmentFilePath(): string
    {
        return $this->environmentPath() . \DIRECTORY_SEPARATOR . $this->environmentFile();
    }
}
