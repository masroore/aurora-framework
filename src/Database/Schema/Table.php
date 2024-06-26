<?php

namespace Aurora\Database\Schema;

use Aurora\Fluent;

class Table
{
    /**
     * The registered custom macros.
     *
     * @var array
     */
    public static $macros = [];

    /**
     * The database table name.
     *
     * @var string
     */
    public $name;

    /**
     * The database connection that should be used.
     *
     * @var string
     */
    public $connection;

    /**
     * Table charset value.
     *
     * @var string
     */
    public $charset = 'utf8';

    /**
     * Character collation to use for the charset. Only used if the charset is also set.
     *
     * @var string
     */
    public $collation;

    /**
     * The engine that should be used for the table.
     *
     * @var string
     */
    public $engine = 'InnoDB';

    /**
     * The columns that should be added to the table.
     *
     * @var array
     */
    public $columns = [];

    /**
     * The commands that should be executed on the table.
     *
     * @var array
     */
    public $commands = [];

    /**
     * Create a new schema table instance.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Dynamically handle calls to custom macros.
     *
     * @param string $method
     */
    public function __call($method, array $parameters)
    {
        if (isset(static::$macros[$method])) {
            array_unshift($parameters, $this);

            return \call_user_func_array(static::$macros[$method], $parameters);
        }

        throw new \Exception("Method [$method] does not exist.");
    }

    /**
     * Registers a custom macro.
     *
     * @param string   $name
     * @param \Closure $macro
     */
    public static function macro($name, $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Indicate that the table should be created.
     *
     * @return Fluent
     */
    public function create()
    {
        return $this->command(__FUNCTION__);
    }

    /**
     * Create a new primary key on the table.
     *
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function primary($columns, $name = null)
    {
        return $this->key(__FUNCTION__, $columns, $name);
    }

    /**
     * Create a command for creating any index.
     *
     * @param string       $type
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function key($type, $columns, $name)
    {
        $columns = (array)$columns;

        // If no index name was specified, we will concatenate the columns and
        // append the index type to the name to generate a unique name for
        // the index that can be used when dropping indexes.
        if (null === $name) {
            $name = str_replace(['-', '.'], '_', $this->name);

            $name = $name . '_' . implode('_', $columns) . '_' . $type;
        }

        return $this->command($type, compact('name', 'columns'));
    }

    /**
     * Create a new unique index on the table.
     *
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function unique($columns, $name = null)
    {
        return $this->key(__FUNCTION__, $columns, $name);
    }

    /**
     * Create a new full-text index on the table.
     *
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function fulltext($columns, $name = null)
    {
        return $this->key(__FUNCTION__, $columns, $name);
    }

    /**
     * Create a new index on the table.
     *
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function index($columns, $name = null)
    {
        return $this->key(__FUNCTION__, $columns, $name);
    }

    /**
     * Add a foreign key constraint to the table.
     *
     * @param array|string $columns
     * @param string       $name
     *
     * @return Fluent
     */
    public function foreign($columns, $name = null)
    {
        return $this->key(__FUNCTION__, $columns, $name);
    }

    /**
     * Rename the database table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function rename($name)
    {
        return $this->command(__FUNCTION__, compact('name'));
    }

    /**
     * Drop the database table.
     *
     * @return Fluent
     */
    public function drop()
    {
        return $this->command(__FUNCTION__);
    }

    /**
     * Drop a column from the table.
     *
     * @param array|string $columns
     */
    public function dropColumn($columns): void
    {
        $this->command(__FUNCTION__, ['columns' => (array)$columns]);
    }

    /**
     * Drop a primary key from the table.
     *
     * @param string $name
     */
    public function dropPrimary($name = null): void
    {
        $this->dropKey(__FUNCTION__, $name);
    }

    /**
     * Drop a unique index from the table.
     *
     * @param string $name
     */
    public function dropUnique($name): void
    {
        $this->dropKey(__FUNCTION__, $name);
    }

    /**
     * Drop a full-text index from the table.
     *
     * @param string $name
     */
    public function dropFulltext($name): void
    {
        $this->dropKey(__FUNCTION__, $name);
    }

    /**
     * Drop an index from the table.
     *
     * @param string $name
     */
    public function dropIndex($name): void
    {
        $this->dropKey(__FUNCTION__, $name);
    }

    /**
     * Drop a foreign key constraint from the table.
     *
     * @param string $name
     */
    public function dropForeign($name): void
    {
        $this->dropKey(__FUNCTION__, $name);
    }

    /**
     * Add an auto-incrementing integer to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function increments($name)
    {
        return $this->integer($name, true);
    }

    /**
     * Add an integer column to the table.
     *
     * @param string $name
     * @param bool   $increment
     *
     * @return Fluent
     */
    public function integer($name, $increment = false)
    {
        return $this->column(__FUNCTION__, compact('name', 'increment'));
    }

    /**
     * Add a string column to the table.
     *
     * @param string $name
     * @param int    $length
     *
     * @return Fluent
     */
    public function string($name, $length = 200)
    {
        return $this->column(__FUNCTION__, compact('name', 'length'));
    }

    /**
     * Add a float column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function float($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Add a decimal column to the table.
     *
     * @param string $name
     * @param int    $precision
     * @param int    $scale
     *
     * @return Fluent
     */
    public function decimal($name, $precision, $scale)
    {
        return $this->column(__FUNCTION__, compact('name', 'precision', 'scale'));
    }

    /**
     * Add a boolean column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function boolean($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Create date-time columns for creation and update timestamps.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();

        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a date-time column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function date($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Add a timestamp column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function timestamp($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Add a text column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function text($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Add a blob column to the table.
     *
     * @param string $name
     *
     * @return Fluent
     */
    public function blob($name)
    {
        return $this->column(__FUNCTION__, compact('name'));
    }

    /**
     * Set the database connection for the table operation.
     *
     * @param string $connection
     */
    public function on($connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Determine if the schema table has a creation command.
     *
     * @return bool
     */
    public function creating()
    {
        return null !== array_first($this->commands, static fn ($key, $value) => 'create' === $value->type);
    }

    /**
     * Create a new fluent command instance.
     *
     * @param string $type
     *
     * @return Fluent
     */
    protected function command($type, array $parameters = [])
    {
        $parameters = array_merge(compact('type'), $parameters);

        return $this->commands[] = new Fluent($parameters);
    }

    /**
     * Create a command to drop any type of index.
     *
     * @param string $type
     * @param string $name
     *
     * @return Fluent
     */
    protected function dropKey($type, $name)
    {
        return $this->command($type, compact('name'));
    }

    /**
     * Create a new fluent column instance.
     *
     * @param string $type
     *
     * @return Fluent
     */
    protected function column($type, array $parameters = [])
    {
        $parameters = array_merge(compact('type'), $parameters);

        return $this->columns[] = new Fluent($parameters);
    }
}
