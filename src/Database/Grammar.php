<?php

namespace Aurora\Database;

abstract class Grammar
{
    /**
     * The keyword identifier for the database system.
     *
     * @var string
     */
    protected $wrapper = '"%s"';

    /**
     * The database connection instance for the grammar.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Create a new database grammar instance.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param string $table
     *
     * @return string
     */
    public function wrapTable($table)
    {
        // Expressions should be injected into the query as raw strings
        // so we do not want to wrap them in any way. We will just return
        // the string value from the expression to be included.
        if ($table instanceof Expression) {
            return $this->wrap($table);
        }

        // Tables may be prefixed with a string. This allows developers to
        // prefix tables by application on the same database which may be
        // required in some brown-field situations.
        $prefix = $this->connection->getTablePrefix();

        return $this->wrap($prefix . $table);
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param string $value
     *
     * @return string
     */
    public function wrap($value)
    {
        // Expressions should be injected into the query as raw strings
        // so we do not want to wrap them in any way. We will just return
        // the string value from the expression to be included.
        if ($value instanceof Expression) {
            return $value->get();
        }

        // If the value being wrapped contains a column alias, we need to
        // wrap it a little differently as each segment must be wrapped
        // and not the entire string.
        if (false !== mb_stripos($value, ' as ')) {
            $segments = explode(' ', $value);

            return sprintf(
                '%s AS %s',
                $this->wrap($segments[0]),
                $this->wrap($segments[2])
            );
        }

        // Since columns may be prefixed with their corresponding table
        // name so as to not make them ambiguous, we will need to wrap
        // the table and the column in keyword identifiers.
        $segments = explode('.', $value);

        foreach ($segments as $k => $v) {
            if (0 === $k && \count($segments) > 1) {
                $wrapped[] = $this->wrapTable($v);
            } else {
                $wrapped[] = $this->wrapValue($v);
            }
        }

        return implode('.', $wrapped);
    }

    /**
     * Create query parameters from an array of values.
     *
     * <code>
     *        Returns "?, ?, ?", which may be used as PDO place-holders
     *        $parameters = $grammar->parameterize(array(1, 2, 3));
     *
     *        // Returns "?, "Taylor"" since an expression is used
     *        $parameters = $grammar->parameterize(array(1, DB::raw('Taylor')));
     * </code>
     *
     * @param array $values
     *
     * @return string
     */
    final public function parameterize($values)
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter string for a value.
     *
     * <code>
     *        // Returns a "?" PDO place-holder
     *        $value = $grammar->parameter('Taylor Otwell');
     *
     *        // Returns "Taylor Otwell" as the raw value of the expression
     *        $value = $grammar->parameter(DB::raw('Taylor Otwell'));
     * </code>
     *
     * @return string
     */
    final public function parameter($value)
    {
        return ($value instanceof Expression) ? $value->get() : '?';
    }

    /**
     * Create a comma-delimited list of wrapped column names.
     *
     * <code>
     *        // Returns ""Taylor", "Otwell"" when the identifier is quotes
     *        $columns = $grammar->columnize(array('Taylor', 'Otwell'));
     * </code>
     *
     * @param array $columns
     *
     * @return string
     */
    final public function columnize($columns)
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Wrap a single string value in keyword identifiers.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapValue($value)
    {
        return ('*' !== $value) ? sprintf($this->wrapper, $value) : $value;
    }
}
