<?php

namespace Aurora\Database\Query;

class Join
{
    /**
     * The type of join being performed.
     *
     * @var string
     */
    public $type;

    /**
     * The table the join clause is joining to.
     *
     * @var string
     */
    public $table;

    /**
     * The ON clauses for the join.
     *
     * @var array
     */
    public $clauses = [];

    /**
     * Create a new query join instance.
     *
     * @param string $type
     * @param string $table
     */
    public function __construct($type, $table)
    {
        $this->type = $type;
        $this->table = $table;
    }

    /**
     * Add an OR ON clause to the join.
     *
     * @param string $column1
     * @param string $operator
     * @param string $column2
     *
     * @return Join
     */
    public function orOn($column1, $operator, $column2)
    {
        return $this->on($column1, $operator, $column2, 'OR');
    }

    /**
     * Add an ON clause to the join.
     *
     * @param string $column1
     * @param string $operator
     * @param string $column2
     * @param string $connector
     *
     * @return Join
     */
    public function on($column1, $operator, $column2, $connector = 'AND')
    {
        $this->clauses[] = compact('column1', 'operator', 'column2', 'connector');

        return $this;
    }
}
