<?php

namespace Aurora\CLI\Tasks;

use Aurora\File;

class Help extends Task
{
    /**
     * List available artisan commands.
     */
    public function commands(): void
    {
        // read help contents
        $command_data = json_decode(File::get(__DIR__ . '/help.json'));

        // format and display help contents
        $i = 0;
        foreach ($command_data as $category => $commands) {
            if (0 !== $i++) {
                echo \PHP_EOL;
            }

            echo \PHP_EOL . "# $category" . \PHP_EOL;

            foreach ($commands as $command => $details) {
                echo \PHP_EOL . str_pad($command, 20) . str_pad($details->description, 30);
            }
        }
    }
}
