<?php

namespace Aurora\CLI\Tasks\Migrate;

use Aurora\Database as DB;
use Aurora\Request;

class Database
{
    public const MIGRATION_TABLE = 'migrations';

    /**
     * Log a migration in the migration table.
     *
     * @param string $bundle
     * @param string $name
     * @param int    $batch
     */
    public function log($bundle, $name, $batch): void
    {
        $this->table()->insert(compact('bundle', 'name', 'batch'));
    }

    /**
     * Delete a row from the migration table.
     *
     * @param string $bundle
     * @param string $name
     */
    public function delete($bundle, $name): void
    {
        $this->table()->whereBundleAndName($bundle, $name)->delete();
    }

    /**
     * Return an array of the last batch of migrations.
     *
     * @return array
     */
    public function last()
    {
        $table = $this->table();

        // First we need to grab the last batch ID from the migration table,
        // as this will allow us to grab the latest batch of migrations
        // that need to be run for a rollback command.
        $id = $this->batch();

        // Once we have the batch ID, we will pull all of the rows for that
        // batch. Then we can feed the results into the resolve method to
        // get the migration instances for the command.
        return $table->whereBatch($id)->orderBy('name', 'desc')->get();
    }

    /**
     * Get the maximum batch ID from the migration table.
     *
     * @return int
     */
    public function batch()
    {
        return $this->table()->max('batch');
    }

    /**
     * Get all of the migrations that have run for a bundle.
     *
     * @param string $bundle
     *
     * @return array
     */
    public function ran($bundle)
    {
        return $this->table()->whereBundle($bundle)->lists('name');
    }

    /**
     * Get a database query instance for the migration table.
     *
     * @return DB\Query
     */
    protected function table()
    {
        return DB::connection(Request::server('cli.db'))->table(self::MIGRATION_TABLE);
    }
}
