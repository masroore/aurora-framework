<?php

namespace Aurora\Database\Query\Grammars;

use Aurora\Database\Query;

class SQLServerGrammar extends Grammar
{
    /**
     * The format for properly saving a DateTime.
     *
     * @var string
     */
    public $datetime = 'Y-m-d H:i:s.000';

    /**
     * The keyword identifier for the database system.
     *
     * @var string
     */
    protected $wrapper = '[%s]';

    /**
     * Compile a SQL SELECT statement from a Query instance.
     *
     * @return string
     */
    public function select(Query $query)
    {
        $sql = parent::compileComponents($query);

        // SQL Server does not currently implement an "OFFSET" type keyword, so we
        // actually have to generate the ANSI standard SQL for doing offset like
        // functionality. OFFSET is in SQL Server 2012, however.
        if ($query->offset > 0) {
            return $this->ansi_offset($query, $sql);
        }

        // Once all of the clauses have been compiled, we can join them all as
        // one statement. Any segments that are null or an empty string will
        // be removed from the array before imploding.
        return $this->concatenate($sql);
    }

    /**
     * Generate the ANSI standard SQL for an offset clause.
     *
     * @param array $components
     *
     * @return array
     */
    protected function ansi_offset(Query $query, $components)
    {
        // An ORDER BY clause is required to make this offset query work, so if
        // one doesn't exist, we'll just create a dummy clause to trick the
        // database and pacify it so it doesn't complain about the query.
        if (!isset($components['orderings'])) {
            $components['orderings'] = 'ORDER BY (SELECT 0)';
        }

        // We need to add the row number to the query so we can compare it to
        // the offset and limit values given for the statement. So we'll add
        // an expression to the select for the row number.
        $orderings = $components['orderings'];

        $components['selects'] .= ", ROW_NUMBER() OVER ({$orderings}) AS RowNum";

        unset($components['orderings']);

        $start = (int)$query->offset + 1;

        // Next we need to calculate the constraint that should be placed on
        // the row number to get the correct offset and limit on the query.
        // If there is not a limit, we'll just handle the offset.
        if ($query->limit > 0) {
            $finish = (int)$query->offset + (int)$query->limit;

            $constraint = "BETWEEN {$start} AND {$finish}";
        } else {
            $constraint = ">= {$start}";
        }

        // We're finally ready to build the final SQL query so we'll create
        // a common table expression with the query and select all of the
        // results with row numbers between the limit and offset.
        $sql = $this->concatenate($components);

        return "SELECT * FROM ($sql) AS TempTable WHERE RowNum {$constraint}";
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

        $select = ($query->distinct) ? 'SELECT DISTINCT ' : 'SELECT ';

        // Instead of using a "LIMIT" keyword, SQL Server uses the TOP keyword
        // within the SELECT statement. So, if we have a limit, we will add
        // it to the query here if there is not an OFFSET present.
        if ($query->limit > 0 && $query->offset <= 0) {
            $select .= 'TOP ' . (int)$query->limit . ' ';
        }

        return $select . $this->columnize($query->selects);
    }

    /**
     * Compile the LIMIT clause for a query.
     *
     * @return string
     */
    protected function compileLimit(Query $query)
    {
        return '';
    }

    /**
     * Compile the OFFSET clause for a query.
     *
     * @return string
     */
    protected function compileOffset(Query $query)
    {
        return '';
    }
}
