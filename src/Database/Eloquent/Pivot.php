<?php

namespace Aurora\Database\Eloquent;

class Pivot extends Model
{
    /**
     * Indicates if the model has update and creation timestamps.
     *
     * @var bool
     */
    public static $timestamps = true;

    /**
     * The name of the pivot table's table.
     *
     * @var string
     */
    protected $pivot_table;

    /**
     * The database connection used for this model.
     *
     * @var Aurora\Database\Connection
     */
    protected $pivot_connection;

    /**
     * Create a new pivot table instance.
     *
     * @param string $table
     * @param string $connection
     */
    public function __construct($table, $connection = null)
    {
        $this->pivot_table = $table;
        $this->pivot_connection = $connection;

        parent::__construct([], true);
    }

    /**
     * Get the name of the pivot table.
     *
     * @return string
     */
    public function table()
    {
        return $this->pivot_table;
    }

    /**
     * Get the connection used by the pivot table.
     *
     * @return string
     */
    public function connection()
    {
        return $this->pivot_connection;
    }
}
