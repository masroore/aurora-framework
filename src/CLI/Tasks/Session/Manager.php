<?php

namespace Aurora\CLI\Tasks\Session;

use Aurora\CLI\Tasks\Task;
use Aurora\Config;
use Aurora\File;
use Aurora\IoC;
use Aurora\Session;
use Aurora\Session\Drivers\Sweeper;

class Manager extends Task
{
    /**
     * Generate the session table on the database.
     *
     * @param array $arguments
     */
    public function table($arguments = []): void
    {
        $migrator = IoC::resolve('task: migrate');

        $key = IoC::resolve('task: key');

        // Since sessions can't work without an application key, we will go
        // ahead and set the key if one has not already been set for the
        // application so the developer doesn't need to set it.
        $key->generate();

        // To create the session table, we will actually create a database
        // migration and then run it. This allows the application to stay
        // portable through the framework's migrations system.
        $migration = $migrator->make(['create_session_table']);

        $stub = SYS_PATH . 'cli/tasks/session/Create_Session_Table' . EXT;

        File::put($migration, File::get($stub));

        // By default no session driver is set within the configuration.
        // Since the developer is requesting that the session table be
        // created on the database, we'll set it.
        $this->driver('database');

        echo \PHP_EOL;

        $migrator->run();
    }

    /**
     * Sweep the expired sessions from storage.
     *
     * @param array $arguments
     */
    public function sweep($arguments = []): void
    {
        $driver = Session::factory(Config::get('session.driver'));

        // If the driver implements the "Sweeper" interface, we know that it
        // can sweep expired sessions from storage. Not all drivers need be
        // sweepers since they do their own.
        if ($driver instanceof Sweeper) {
            $lifetime = Config::get('session.lifetime');

            $driver->sweep(time() - ($lifetime * 60));
        }

        echo 'The session table has been swept!';
    }

    /**
     * Set the session driver to a given value.
     *
     * @param string $driver
     */
    protected function driver($driver): void
    {
        // By default no session driver is set within the configuration.
        // This method will replace the empty driver option with the
        // driver specified in the arguments.
        $config = File::get(APP_PATH . 'config/session' . EXT);

        $config = str_replace(
            "'driver' => '',",
            "'driver' => 'database',",
            $config
        );

        File::put(APP_PATH . 'config/session' . EXT, $config);
    }
}
