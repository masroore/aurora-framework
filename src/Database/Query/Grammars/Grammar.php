<?php

namespace Aurora\Database\Query\Grammars;

use Aurora\Database\Grammar as BaseGrammar;
use Aurora\Database\Query;

class Grammar extends BaseGrammar
{
    /**
     * The format for properly saving a DateTime.
     *
     * @var string
     */
    public $datetime = 'Y-m-d H:i:s';

    /**
     * All of the query components in the order they should be built.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'selects',
        'from',
        'joins',
        'wheres',
        'groupings',
        'havings',
        'orderings',
        'limit',
        'offset',
    ];

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
        return $this->insert($query, $values);
    }

    /**
     * Compile a SQL INSERT statement from a Query instance.
     *
     * This method handles the compilation of single row inserts and batch inserts.
     *
     * @return string
     */
    public function insert(Query $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (!\is_array(reset($values))) {
            $values = [$values];
        }

        // Since we only care about the column names, we can pass any of the insert
        // arrays into the "columnize" method. The columns should be the same for
        // every record inserted into the table.
        $columns = $this->columnize(array_keys(reset($values)));

        // Build the list of parameter place-holders of values bound to the query.
        // Each insert should have the same number of bound parameters, so we can
        // just use the first array of values.
        $parameters = $this->parameterize(reset($values));

        $value = array_fill(0, \count($values), "($parameters)");

        $parameters = implode(', ', $value);

        return "INSERT INTO {$table} ({$columns}) VALUES {$parameters}";
    }

    /**
     * Compile a SQL UPDATE statement from a Query instance.
     *
     * @return string
     */
    public function update(Query $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = $this->compileUpdateColumns($values);

        // UPDATE statements may be constrained by a WHERE clause, so we'll run
        // the entire where compilation process for those constraints. This is
        // easily achieved by passing it to the "wheres" method.
        return trim("UPDATE {$table} SET {$columns} " . $this->compileWheres($query));
    }

    /**
     * Compile a SQL DELETE statement from a Query instance.
     *
     * @return string
     */
    public function delete(Query $query)
    {
        $table = $this->wrapTable($query->from);

        $wheres = \is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("DELETE FROM {$table} " . $wheres);
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param string    $column
     * @param float|int $amount
     *
     * @return int
     */
    public function increment(Query $query, $column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->wrap($column);

        $columns = array_merge([$column => \DB::raw("$wrapped + $amount")], $extra);

        return $this->update($query, $columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string    $column
     * @param float|int $amount
     *
     * @return int
     */
    public function decrement(Query $query, $column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->wrap($column);

        $columns = array_merge([$column => \DB::raw("$wrapped - $amount")], $extra);

        return $this->update($query, $columns);
    }

    /**
     * Transform an SQL short-cuts into real SQL for PDO.
     *
     * @param string $sql
     *
     * @return string
     */
    public function shortcut($sql, array &$bindings)
    {
        // Aurora provides an easy short-cut notation for writing raw WHERE IN
        // statements. If (...) is in the query, it will be replaced with the
        // correct number of parameters based on the query bindings.
        if (false !== mb_strpos($sql, '(...)')) {
            for ($i = 0, $count = \count($bindings); $i < $count; ++$i) {
                // If the binding is an array, we can just assume it's used to fill a
                // where in condition, so we'll just replace the next place-holder
                // in the query with the constraint and splice the bindings.
                if (\is_array($bindings[$i])) {
                    $parameters = $this->parameterize($bindings[$i]);

                    array_splice($bindings, $i, 1, $bindings[$i]);

                    $sql = preg_replace('~\(\.\.\.\)~', "({$parameters})", $sql, 1);
                }
            }
        }

        return trim($sql);
    }

    /**
     * Compile a select query into SQL.
     *
     * @param  Query
     *
     * @return string
     */
    public function select(Query $query)
    {
        if (null === $query->selects) {
            $query->selects = ['*'];
        }

        return trim($this->concatenate($this->compileComponents($query)));
    }

    /**
     * Concatenate an array of SQL segments, removing those that are empty.
     *
     * @return string
     */
    final protected function concatenate(array $segments)
    {
        return implode(' ', array_filter($segments, static fn ($value) => '' !== (string)$value));
    }

    /**
     * Generate the SQL for every component of the query.
     *
     * @return array
     */
    final protected function compileComponents(Query $query)
    {
        // Each portion of the statement is compiled by a function corresponding
        // to an item in the components array. This lets us to keep the creation
        // of the query very granular and very flexible.
        foreach ($this->selectComponents as $component) {
            if (null !== $query->$component) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = \call_user_func([$this, $method], $query);
            }
        }

        return (array)$sql;
    }

    /**
     * Compile the columns for the update statement.
     *
     * @param array $values
     *
     * @return string
     */
    protected function compileUpdateColumns($values)
    {
        $columns = [];

        // When gathering the columns for an update statement, we'll wrap each of the
        // columns and convert it to a parameter value. Then we will concatenate a
        // list of the columns that can be added into this update query clauses.
        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ' . $this->parameter($value);
        }

        return implode(', ', $columns);
    }

    /**
     * Compile the SELECT clause for a query.
     *
     * @return string
     */
    protected function compileSelects(Query $query)
    {
        if (null !== $query->aggregate) {
            return;
        }

        $select = $query->distinct ? 'SELECT DISTINCT ' : 'SELECT ';

        return $select . $this->columnize($query->selects);
    }

    /**
     * Compile an aggregating SELECT clause for a query.
     *
     * @return string
     */
    protected function compileAggregate(Query $query)
    {
        $column = $this->columnize($query->aggregate['columns']);

        // If the "distinct" flag is set and we're not aggregating everything
        // we'll set the distinct clause on the query, since this is used
        // to count all of the distinct values in a column, etc.
        if ($query->distinct && '*' !== $column) {
            $column = 'DISTINCT ' . $column;
        }

        return 'SELECT ' . $query->aggregate['aggregator'] . '(' . $column . ') AS ' . $this->wrap('aggregate');
    }

    /**
     * Compile the FROM clause for a query.
     *
     * @return string
     */
    protected function compileFrom(Query $query)
    {
        return 'FROM ' . $this->wrapTable($query->from);
    }

    /**
     * Compile the JOIN clauses for a query.
     *
     * @return string
     */
    protected function compileJoins(Query $query)
    {
        $sql = [];

        // We need to iterate through each JOIN clause that is attached to the
        // query and translate it into SQL. The table and the columns will be
        // wrapped in identifiers to avoid naming collisions.
        foreach ($query->joins as $join) {
            $table = $this->wrapTable($join->table);

            $clauses = [];

            // Each JOIN statement may have multiple clauses, so we will iterate
            // through each clause creating the conditions then we'll join all
            // of them together at the end to build the clause.
            foreach ($join->clauses as $clause) {
                $clauses[] = $this->compileJoinConstraint($clause);
            }

            // The first clause will have a connector on the front, but it is
            // not needed on the first condition, so we will strip it off of
            // the condition before adding it to the array of joins.
            $clauses[0] = $this->removeLeadingBoolean($clauses[0]);

            $clauses = implode(' ', $clauses);

            $type = $join->type;

            // Once we have everything ready to go, we will just concatenate all the parts to
            // build the final join statement SQL for the query and we can then return the
            // final clause back to the callers as a single, stringified join statement.
            $sql[] = "{$type} JOIN {$table} ON {$clauses}";
        }

        // Finally, we should have an array of JOIN clauses that we can
        // implode together and return as the complete SQL for the
        // join clause of the query under construction.
        return implode(' ', $sql);
    }

    /**
     * Create a join clause constraint segment.
     *
     * @return string
     */
    protected function compileJoinConstraint(array $clause)
    {
        extract($clause, \EXTR_OVERWRITE);

        $column1 = $this->wrap($column1);

        $column2 = $this->wrap($column2);

        return "{$connector} {$column1} {$operator} {$column2}";
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param string $value
     *
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/AND |OR /', '', $value, 1);
    }

    /**
     * Compile a nested WHERE clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereNested($where)
    {
        return '(' . mb_substr($this->compileWheres($where['query']), 6) . ')';
    }

    /**
     * Compile the WHERE clause for a query.
     *
     * @return string
     */
    final protected function compileWheres(Query $query)
    {
        if (null === $query->wheres) {
            return '';
        }

        $sql = [];
        // Each WHERE clause array has a "type" that is assigned by the query
        // builder, and each type has its own compiler function. We will call
        // the appropriate compiler for each where clause.
        foreach ($query->wheres as $where) {
            $sql[] = mb_strtoupper($where['connector']) . ' ' . $this->{$where['type']}($where);
        }

        if (isset($sql)) {
            // We attach the boolean connector to every where segment just
            // for convenience. Once we have built the entire clause we'll
            // remove the first instance of a connector.
            return 'WHERE ' . preg_replace('/AND |OR /', '', implode(' ', $sql), 1);
        }
    }

    /**
     * Compile a simple WHERE clause.
     *
     * @return string
     */
    protected function where(array $where)
    {
        $parameter = $this->parameter($where['value']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $parameter;
    }

    /**
     * Compile a WHERE IN clause.
     *
     * @return string
     */
    protected function whereIn(array $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) . ' IN (' . $values . ')';
    }

    /**
     * Compile a WHERE NOT IN clause.
     *
     * @return string
     */
    protected function whereNotIn(array $where)
    {
        if (empty($where['values'])) {
            return '1 = 1';
        }

        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) . ' NOT IN (' . $values . ')';
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @return string
     */
    protected function whereInSub(array $where)
    {
        $select = $this->select($where['query']);

        return $this->wrap($where['column']) . ' IN (' . $select . ')';
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @return string
     */
    protected function whereNotInSub(array $where)
    {
        $select = $this->select($where['query']);

        return $this->wrap($where['column']) . ' NOT IN (' . $select . ')';
    }

    /**
     * Compile a WHERE BETWEEN clause.
     *
     * @return string
     */
    protected function whereBetween(array $where)
    {
        $min = $this->parameter($where['min']);
        $max = $this->parameter($where['max']);

        return $this->wrap($where['column']) . ' BETWEEN ' . $min . ' AND ' . $max;
    }

    /**
     * Compile a WHERE NOT BETWEEN clause.
     *
     * @return string
     */
    protected function whereNotBetween(array $where)
    {
        $min = $this->parameter($where['min']);
        $max = $this->parameter($where['max']);

        return $this->wrap($where['column']) . ' NOT BETWEEN ' . $min . ' AND ' . $max;
    }

    /**
     * Compile a where exists clause.
     *
     * @return string
     */
    protected function whereExists(array $where)
    {
        return 'EXISTS (' . $this->select($where['query']) . ')';
    }

    /**
     * Compile a where exists clause.
     *
     * @return string
     */
    protected function whereNotExists(array $where)
    {
        return 'NOT EXISTS (' . $this->select($where['query']) . ')';
    }

    /**
     * Compile a WHERE NULL clause.
     *
     * @return string
     */
    protected function whereNull(array $where)
    {
        return $this->wrap($where['column']) . ' IS NULL';
    }

    /**
     * Compile a WHERE NULL clause.
     *
     * @return string
     */
    protected function whereNotNull(array $where)
    {
        return $this->wrap($where['column']) . ' IS NOT NULL';
    }

    /**
     * Compile a raw WHERE clause.
     *
     * @return string
     */
    final protected function whereRaw(array $where)
    {
        return $where['sql'];
    }

    /**
     * Compile a where condition with a sub-select.
     *
     * @return string
     */
    protected function whereSub(array $where)
    {
        $select = $this->select($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ($select)";
    }

    /**
     * Compile the GROUP BY clause for a query.
     *
     * @return string
     */
    protected function compileGroupings(Query $query)
    {
        return 'GROUP BY ' . $this->columnize($query->groupings);
    }

    /**
     * Compile the HAVING clause for a query.
     *
     * @return string
     */
    protected function compileHavings(Query $query, array $havings)
    {
        if (null === $query->havings) {
            return '';
        }

        $sql = implode(' ', array_map([$this, 'compileHaving'], $query->havings));

        return 'HAVING ' . preg_replace('/AND |OR /', '', $sql, 1);
    }

    /**
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ('raw' === $having['type']) {
            return mb_strtoupper($having['boolean']) . ' ' . $having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param array $having
     *
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return mb_strtoupper($having['boolean']) . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * Compile the ORDER BY clause for a query.
     *
     * @return string
     */
    protected function compileOrderings(Query $query)
    {
        foreach ($query->orderings as $ordering) {
            $sql[] = $this->wrap($ordering['column']) . ' ' . mb_strtoupper($ordering['direction']);
        }

        return 'ORDER BY ' . implode(', ', $sql);
    }

    /**
     * Compile the LIMIT clause for a query.
     *
     * @return string
     */
    protected function compileLimit(Query $query)
    {
        return 'LIMIT ' . (int)$query->limit;
    }

    /**
     * Compile the OFFSET clause for a query.
     *
     * @return string
     */
    protected function compileOffset(Query $query)
    {
        return 'OFFSET ' . (int)$query->offset;
    }

    /**
     * Compile a date based where clause.
     *
     * @param string $type
     * @param array  $where
     *
     * @return string
     */
    protected function dateBasedWhere($type, $where)
    {
        $value = $this->parameter($where['value']);

        return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a "where date" clause.
     *
     * @return string
     */
    protected function whereDate(array $where)
    {
        return $this->dateBasedWhere('date', $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @return string
     */
    protected function whereDay(array $where)
    {
        return $this->dateBasedWhere('day', $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @return string
     */
    protected function whereMonth(array $where)
    {
        return $this->dateBasedWhere('month', $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @return string
     */
    protected function whereYear(array $where)
    {
        return $this->dateBasedWhere('year', $where);
    }
}
