<?php

namespace Aurora\CLI\Tasks;

class Serve extends Task
{
    /**
     * Run a database migration command.
     */
    public function run(array $arguments = []): void
    {
        try {
            $this->checkPhpVersion();
        } catch (\Exception $e) {
        }

        $host = get_cli_option('host', 'localhost');

        $port = get_cli_option('port', '8000');

        // $public = rtrim(path('public'), DS);
        $public = AURORA_ROOT;

        $this->croak("Aurora development server listen on http://{$host}:{$port}");

        ob_end_flush();

        // little sugar for windows
        if (0 === mb_strpos(\PHP_OS, 'WIN')) {
            passthru("start http://{$host}:{$port}");
        }

        passthru('"' . \PHP_BINARY . '"' . " -S {$host}:{$port} -t \"{$public}\" server.php");
    }

    /**
     * Check the current PHP version is >= 5.4.
     */
    protected function checkPhpVersion(): void
    {
        if (version_compare(\PHP_VERSION, '5.4.0', '<')) {
            throw new \Exception('This PHP binary is not version 5.4 or greater.');
        }
    }
}
