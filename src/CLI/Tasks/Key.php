<?php

namespace Aurora\CLI\Tasks;

use Aurora\Config;
use Aurora\File;
use Aurora\Request;
use Aurora\Str;

class Key extends Task
{
    /**
     * The path to the application config.
     *
     * @var string
     */
    protected $path;

    /**
     * The application environment.
     *
     * @var string
     */
    private $env;

    /**
     * Create a new instance of the Key task.
     */
    public function __construct()
    {
        $this->env = Request::env() ? Request::env() . DS : '';
        $this->path = APP_PATH . "config/{$this->env}application" . EXT;
    }

    /**
     * Generate a random key for the application.
     */
    public function generate(array $arguments = []): void
    {
        // By default the Crypt class uses AES-256 encryption which uses
        // a 32 byte input vector, so that is the length of string we will
        // generate for the application token unless another length is
        // specified through the CLI.
        $key = Str::random(array_get($arguments, 0, 32));
        if (File::exists($this->path)) {
            $config = File::get($this->path);

            $key_placeholder = Config::get('app.key');

            $config = str_replace("'key' => '{$key_placeholder}'", "'key' => '{$key}'", $config);

            File::put($this->path, $config);

            $this->croak("An application key {$key} set successfully.");
        } else {
            $this->croak("config/{$this->env}application.php does not exist!");
        }
    }
}
