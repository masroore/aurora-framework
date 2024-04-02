<?php

namespace Aurora\Database\Query\Grammars;

use Aurora\Database\Query;

class PostgresGrammar extends Grammar
{
    /**
     * Compile a SQL INSERT and get ID statement from a Query instance.
     *
     * @param array  $values
     * @param string $column
     *
     * @return string
     */
    public function insertGetId(Query $query, $values, $column)
    {
        return $this->insert($query, $values) . " RETURNING $column";
    }
}
