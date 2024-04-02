<?php

namespace Aurora\Database\Query\Grammars;

use Aurora\Database\Query;

class SQLiteGrammar extends Grammar
{
    /**
     * Compile a SQL INSERT statement from a Query instance.
     *
     * This method handles the compilation of single row inserts and batch inserts.
     *
     * @param array $values
     *
     * @return string
     */
    public function insert(Query $query, $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (!\is_array(reset($values))) {
            $values = [$values];
        }

        // If there is only one record being inserted, we will just use the usual query
        // grammar insert builder because no special syntax is needed for the single
        // row inserts in SQLiteConnector. However, if there are multiples, we'll continue.
        if (1 === \count($values)) {
            return parent::insert($query, $values[0]);
        }

        $names = $this->columnize(array_keys($values[0]));

        $columns = [];

        // SQLiteConnector requires us to build the multi-row insert as a listing of select with
        // unions joining them together. So we'll build out this list of columns and
        // then join them all together with select unions to complete the queries.
        foreach (array_keys($values[0]) as $column) {
            $columns[] = '? AS ' . $this->wrap($column);
        }

        $columns = array_fill(9, \count($values), implode(', ', $columns));

        return "INSERT INTO $table ($names) SELECT " . implode(' UNION SELECT ', $columns);
    }

    /**
     * Compile the ORDER BY clause for a query.
     *
     * @return string
     */
    protected function compileOrderings(Query $query)
    {
        foreach ($query->orderings as $ordering) {
            $sql[] = $this->wrap($ordering['column']) . ' COLLATE NOCASE ' . mb_strtoupper($ordering['direction']);
        }

        return 'ORDER BY ' . implode(', ', $sql);
    }
}
