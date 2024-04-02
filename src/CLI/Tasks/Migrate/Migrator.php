<?php

namespace Aurora\CLI\Tasks\Migrate;

use Aurora\Bundle;
use Aurora\CLI\Tasks\Task;
use Aurora\Database\Schema;
use Aurora\File;
use Aurora\Str;

class Migrator extends Task
{
    /**
     * The migration resolver instance.
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * The migration database instance.
     *
     * @var Database
     */
    protected $database;

    /**
     * Create a new instance of the Migrator CLI task.
     */
    public function __construct(Resolver $resolver, Database $database)
    {
        $this->resolver = $resolver;
        $this->database = $database;
    }

    /**
     * Run a database migration command.
     */
    public function run(array $arguments = []): void
    {
        // If no arguments were passed to the task, we will just migrate
        // to the latest version across all bundles. Otherwise, we will
        // parse the arguments to determine the bundle for which the
        // database migrations should be run.
        if (0 === \count($arguments)) {
            $this->migrate();
        } else {
            $this->migrate(array_get($arguments, 0));
        }
    }

    /**
     * Run the outstanding migrations for a given bundle.
     *
     * @param string $bundle
     * @param int    $version
     */
    public function migrate($bundle = null, $version = null): void
    {
        $migrations = $this->resolver->outstanding($bundle);

        if (0 === \count($migrations)) {
            echo 'No outstanding migrations.';

            return;
        }

        // We need to grab the latest batch ID and increment it by one.
        // This allows us to group the migrations so we can easily
        // determine which migrations need to roll back.
        $batch = $this->database->batch() + 1;

        foreach ($migrations as $migration) {
            $migration['migration']->up();

            echo 'Migrated: ' . $this->display($migration) . \PHP_EOL;

            // After running a migration, we log its execution in the migration
            // table so that we can easily determine which migrations we'll
            // reverse in the event of a migration rollback.
            $this->database->log($migration['bundle'], $migration['name'], $batch);
        }
    }

    /**
     * Reset the database to pristine state and run all migrations.
     */
    public function rebuild(): void
    {
        // Clean the database
        $this->reset();

        echo \PHP_EOL;

        // Re-run all migrations
        $this->migrate();

        echo 'The database was successfully rebuilt' . \PHP_EOL;
    }

    /**
     * Rollback all of the executed migrations.
     */
    public function reset(array $arguments = []): void
    {
        while ($this->rollback($arguments)) {
        }
    }

    /**
     * Rollback the latest migration command.
     *
     * @return bool
     */
    public function rollback(array $arguments = [])
    {
        $migrations = $this->resolver->last();

        // If bundles supplied, filter migrations to rollback only bundles'
        // migrations.
        if (\count($arguments) > 0) {
            $bundles = $arguments;

            if (!\is_array($bundles)) {
                $bundles = [$bundles];
            }

            $migrations = array_filter($migrations, static fn ($migration) => \in_array($migration['bundle'], $bundles, true));
        }

        if (0 === \count($migrations)) {
            echo 'Nothing to rollback.' . \PHP_EOL;

            return false;
        }

        // The "last" method on the resolver returns an array of migrations,
        // along with their bundles and names. We will iterate through each
        // migration and run the "down" method.
        foreach (array_reverse($migrations) as $migration) {
            $migration['migration']->down();

            echo 'Rolled back: ' . $this->display($migration) . \PHP_EOL;

            // By only removing the migration after it has successfully rolled back,
            // we can re-run the rollback command in the event of any errors with
            // the migration and pick up where we left off.
            $this->database->delete($migration['bundle'], $migration['name']);
        }

        return true;
    }

    /**
     * Install the database tables used by the migration system.
     */
    public function install(): void
    {
        Schema::table(Database::MIGRATION_TABLE, static function ($table): void {
            $table->create();

            // Migrations can be run for a specific bundle, so we'll use
            // the bundle name and string migration name as a unique ID
            // for the migrations, allowing us to easily identify which
            // migrations have been run for each bundle.
            $table->string('bundle', 50);

            $table->string('name', 200);

            // When running a migration command, we will store a batch
            // ID with each of the rows on the table. This will allow
            // us to grab all of the migrations that were run for the
            // last command when performing rollbacks.
            $table->integer('batch');

            $table->primary(['bundle', 'name']);
        });

        echo 'Migration table created successfully.' . \PHP_EOL;
    }

    /**
     * Generate a new migration file.
     *
     * @return string
     */
    public function make(array $arguments = [])
    {
        if (0 === \count($arguments)) {
            throw new \Exception('I need to know what to name the migration.');
        }

        [$bundle, $migration] = Bundle::parse($arguments[0]);

        // The migration path is prefixed with the date timestamp, which
        // is a better way of ordering migrations than a simple integer
        // incrementation, since developers may start working on the
        // next migration at the same time unknowingly.
        $prefix = date('Y_m_d_His');

        $path = Resolver::resolvePath($bundle);

        // If the migration directory does not exist for the bundle,
        // we will create the directory so there aren't errors when
        // when we try to write the migration file.
        if (!is_dir($path)) {
            mkdir($path);
        }

        $file = $path . $prefix . '_' . $migration . EXT;

        File::put($file, $this->stub($bundle, $migration));

        echo 'Great! New migration created!';

        // Once the migration has been created, we'll return the
        // migration file name so it can be used by the task
        // consumer if necessary for further work.
        return $file;
    }

    /**
     * Get the migration bundle and name for display.
     *
     * @param array $migration
     *
     * @return string
     */
    protected function display($migration)
    {
        return $migration['bundle'] . '/' . $migration['name'];
    }

    /**
     * Get the stub migration with the proper class name.
     *
     * @param string $bundle
     * @param string $migration
     *
     * @return string
     */
    protected function stub($bundle, $migration)
    {
        $stub = File::get(SYS_PATH . 'CLI/Tasks/Migrate/migration.stub');

        $prefix = Bundle::classPrefix($bundle);

        // The class name is formatted similarly to tasks and controllers,
        // where the bundle name is prefixed to the class if it is not in
        // the default "application" bundle.
        $class = $prefix . Str::classify($migration);

        return str_replace('{{class}}', $class, $stub);
    }
}
