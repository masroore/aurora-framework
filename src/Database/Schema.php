<?php

namespace Aurora\Database;

use Aurora\Database as DB;
use Aurora\Fluent;
use Closure;

class Schema
{
    /**
     * Begin a fluent schema operation on a database table.
     *
     * @param string $table
     */
    public static function table($table, \Closure $callback): void
    {
        \call_user_func($callback, $t = new Schema\Table($table));

        static::execute($t);
    }

    /**
     * Execute the given schema operation against the database.
     *
     * @param Schema\Table $table
     */
    public static function execute($table): void
    {
        // The implications method is responsible for finding any fluently
        // defined indexes on the schema table and adding the explicit
        // commands that are needed for the schema instance.
        static::implications($table);

        foreach ($table->commands as $command) {
            $connection = DB::connection($table->connection);

            $grammar = static::grammar($connection);

            // Each grammar has a function that corresponds to the command type and
            // is for building that command's SQL. This lets the SQL syntax builds
            // stay granular across various database systems.
            if (method_exists($grammar, $method = $command->type)) {
                $statements = $grammar->$method($table, $command);

                // Once we have the statements, we will cast them to an array even
                // though not all of the commands return an array just in case it
                // needs multiple queries to complete.
                foreach ((array)$statements as $statement) {
                    $connection->query($statement);
                }
            }
        }
    }

    /**
     * Create the appropriate schema grammar for the driver.
     *
     * @return Grammar
     */
    public static function grammar(Connection $connection)
    {
        $driver = $connection->driver();

        if (isset(DB::$registrar[$driver])) {
            return DB::$registrar[$driver]['schema']();
        }

        switch ($driver) {
            case 'mysql':
                return new Schema\Grammars\MySQL($connection);

            case 'pgsql':
                return new Schema\Grammars\Postgres($connection);

            case 'sqlsrv':
                return new Schema\Grammars\SQLServer($connection);

            case 'sqlite':
                return new Schema\Grammars\SQLite($connection);
        }

        throw new \‌‌Exception("Schema operations not supported for [$driver].");
    }

    /**
     * Create a new database table schema.
     *
     * @param string $table
     */
    public static function create($table, \Closure $callback): void
    {
        $t = new Schema\Table($table);

        // To indicate that the table is new and needs to be created, we'll run
        // the "create" command on the table instance. This tells schema it is
        // not simply a column modification operation.
        $t->create();

        \call_user_func($callback, $t);

        static::execute($t);
    }

    /**
     * Rename a database table in the schema.
     *
     * @param string $table
     * @param string $new_name
     */
    public static function rename($table, $new_name): void
    {
        $t = new Schema\Table($table);

        // To indicate that the table needs to be renamed, we will run the
        // "rename" command on the table instance and pass the instance to
        // the execute method as calling a Closure isn't needed.
        $t->rename($new_name);

        static::execute($t);
    }

    /**
     * Drop a database table from the schema.
     *
     * @param string $table
     * @param string $connection
     */
    public static function drop($table, $connection = null): void
    {
        $t = new Schema\Table($table);

        $t->on($connection);

        // To indicate that the table needs to be dropped, we will run the
        // "drop" command on the table instance and pass the instance to
        // the execute method as calling a Closure isn't needed.
        $t->drop();

        static::execute($t);
    }

    /**
     * Add any implicit commands to the schema table operation.
     *
     * @param Schema\Table $table
     */
    protected static function implications($table): void
    {
        // If the developer has specified columns for the table and the table is
        // not being created, we'll assume they simply want to add the columns
        // to the table and generate the add command.
        if (\count($table->columns) > 0 && !$table->creating()) {
            $command = new Fluent(['type' => 'add']);

            array_unshift($table->commands, $command);
        }

        // For some extra syntax sugar, we'll check for any implicit indexes
        // on the table since the developer may specify the index type on
        // the fluent column declaration for convenience.
        foreach ($table->columns as $column) {
            foreach (['primary', 'unique', 'fulltext', 'index'] as $key) {
                if (isset($column->$key)) {
                    if ($column->$key === true) {
                        $table->$key($column->name);
                    } else {
                        $table->$key($column->name, $column->$key);
                    }
                }
            }
        }
    }
}
