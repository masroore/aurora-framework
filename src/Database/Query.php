<?php

namespace Aurora\Database;

use Aurora\Database\Query\Grammars\PostgresGrammar;
use Aurora\Database\Query\Grammars\SQLServerGrammar;
use Aurora\Pagination\AbstractPaginator;
use Aurora\Str;
use Closure;
use Paginator;

class Query
{
    /**
     * The database connection.
     *
     * @var Connection
     */
    public $connection;

    /**
     * The query grammar instance.
     *
     * @var Query\Grammars\Grammar
     */
    public $grammar;

    /**
     * The SELECT clause.
     *
     * @var array
     */
    public $selects;

    /**
     * The aggregating column and function.
     *
     * @var array
     */
    public $aggregate;

    /**
     * Indicates if the query should return distinct results.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * The table name.
     *
     * @var string
     */
    public $from;

    /**
     * The table joins.
     *
     * @var array
     */
    public $joins;

    /**
     * The WHERE clauses.
     *
     * @var array
     */
    public $wheres;

    /**
     * The GROUP BY clauses.
     *
     * @var array
     */
    public $groupings;

    /**
     * The HAVING clauses.
     *
     * @var array
     */
    public $havings;

    /**
     * The ORDER BY clauses.
     *
     * @var array
     */
    public $orderings;

    /**
     * The LIMIT value.
     *
     * @var int
     */
    public $limit;

    /**
     * The OFFSET value.
     *
     * @var int
     */
    public $offset;

    /**
     * The query value bindings.
     *
     * @var array
     */
    public $bindings = [];

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
    ];

    /**
     * Create a new query instance.
     *
     * @param string $table
     */
    public function __construct(Connection $connection, Query\Grammars\Grammar $grammar, $table)
    {
        $this->from = $table;
        $this->grammar = $grammar;
        $this->connection = $connection;
    }

    /**
     * Magic Method for handling dynamic functions.
     *
     * This method handles calls to aggregates as well as dynamic where clauses.
     *
     * @param string $method
     *
     * @return mixed|Query
     */
    public function __call($method, array $parameters)
    {
        if (Str::startsWith($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        // All of the aggregate methods are handled by a single method, so we'll
        // catch them all here and then pass them off to the agregate method
        // instead of creating methods for each one of them.
        if (\in_array($method, ['count', 'min', 'max', 'avg', 'sum'], true)) {
            if (0 === \count($parameters)) {
                $parameters[0] = '*';
            }

            return $this->aggregate(mb_strtoupper($method), (array)$parameters[0]);
        }

        throw new \Exception("Method [$method] is not defined on the Query class.");
    }

    /**
     * Force the query to return distinct results.
     *
     * @return Query
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param string $table
     * @param string $column1
     * @param string $operator
     * @param string $column2
     *
     * @return Query
     */
    public function leftJoin($table, $column1, $operator = null, $column2 = null)
    {
        return $this->join($table, $column1, $operator, $column2, 'LEFT');
    }

    /**
     * Add a join clause to the query.
     *
     * @param string $table
     * @param string $column1
     * @param string $operator
     * @param string $column2
     * @param string $type
     *
     * @return Query
     */
    public function join($table, $column1, $operator = null, $column2 = null, $type = 'INNER')
    {
        // If the "column" is really an instance of a Closure, the developer is
        // trying to create a join with a complex "ON" clause. So, we will add
        // the join, and then call the Closure with the join/
        if ($column1 instanceof \Closure) {
            $this->joins[] = new Query\Join($type, $table);

            \call_user_func($column1, end($this->joins));
        }

        // If the column is just a string, we can assume that the join just
        // has a simple on clause, and we'll create the join instance and
        // add the clause automatically for the develoepr.
        else {
            $join = new Query\Join($type, $table);

            $join->on($column1, $operator, $column2);

            $this->joins[] = $join;
        }

        return $this;
    }

    /**
     * Reset the where clause to its initial state.
     */
    public function resetWhere(): void
    {
        [$this->wheres, $this->bindings] = [[], []];
    }

    /**
     * Add a raw or where condition to the query.
     *
     * @param string $where
     *
     * @return Query
     */
    public function rawOrWhere($where, array $bindings = [])
    {
        return $this->rawWhere($where, $bindings, 'OR');
    }

    /**
     * Add a raw where condition to the query.
     *
     * @param string $where
     * @param string $connector
     *
     * @return Query
     */
    public function rawWhere($where, array $bindings = [], $connector = 'AND')
    {
        $this->wheres[] = ['type' => 'whereRaw', 'connector' => $connector, 'sql' => $where];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Add an or where condition for the primary key to the query.
     *
     * @return Query
     */
    public function orWhereId($value)
    {
        return $this->orWhere('id', '=', $value);
    }

    /**
     * Add an or where condition to the query.
     *
     * @param string     $column
     * @param string     $operator
     * @param mixed|null $value
     *
     * @return Query
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add a where condition to the query.
     *
     * @param string     $column
     * @param string     $operator
     * @param string     $connector
     * @param mixed|null $value
     *
     * @return Query
     */
    public function where($column, $operator = null, $value = null, $connector = 'AND')
    {
        // If a Closure is passed into the method, it means a nested where
        // clause is being initiated, so we will take a different course
        // of action than when the statement is just a simple where.
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $connector);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if (!\in_array(mb_strtolower($operator), $this->operators, true)) {
            [$value, $operator] = [$operator, '='];
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (null === $value) {
            return $this->whereNull($column, $connector, '=' !== $operator);
        }

        $type = 'where';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'connector');

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add a nested where condition to the query.
     *
     * @param string $connector
     *
     * @return Query
     */
    public function whereNested(\Closure $callback, $connector = 'AND')
    {
        $type = 'whereNested';

        // To handle a nested where statement, we will actually instantiate a new
        // Query instance and run the callback over that instance, which will
        // allow the developer to have a fresh query instance
        $query = new self($this->connection, $this->grammar, $this->from);

        \call_user_func($callback, $query);

        // Once the callback has been run on the query, we will store the nested
        // query instance on the where clause array so that it's passed to the
        // query's query grammar instance when building.
        if (null !== $query->wheres) {
            $this->wheres[] = compact('type', 'query', 'connector');
        }

        $this->bindings = array_merge($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Add an or where in condition to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return Query
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Add a where in condition to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $connector
     * @param bool   $not
     *
     * @return Query
     */
    public function whereIn($column, $values, $connector = 'AND', $not = false)
    {
        $type = $not ? 'whereNotIn' : 'whereIn';

        $this->wheres[] = compact('type', 'column', 'values', 'connector');

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add an or where not in condition to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return Query
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * Add a where not in condition to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $connector
     *
     * @return Query
     */
    public function whereNotIn($column, $values, $connector = 'AND')
    {
        return $this->whereIn($column, $values, $connector, true);
    }

    /**
     * Add a OR BETWEEN condition to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function orWhereBetween($column, $min, $max)
    {
        return $this->whereBetween($column, $min, $max, 'OR');
    }

    /**
     * Add a BETWEEN condition to the query.
     *
     * @param string $column
     * @param string $connector
     * @param bool   $not
     *
     * @return Query
     */
    public function whereBetween($column, $min, $max, $connector = 'AND', $not = false)
    {
        $type = $not ? 'whereNotBetween' : 'whereBetween';

        $this->wheres[] = compact('type', 'column', 'min', 'max', 'connector');

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * Add a OR NOT BETWEEN condition to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function orWhereNotBetween($column, $min, $max)
    {
        return $this->whereNotBetween($column, $min, $max, 'OR');
    }

    /**
     * Add a NOT BETWEEN condition to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function whereNotBetween($column, $min, $max, $connector = 'AND')
    {
        return $this->whereBetween($column, $min, $max, $connector, true);
    }

    /**
     * Add an or where null condition to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Add a where null condition to the query.
     *
     * @param string $column
     * @param string $connector
     * @param bool   $not
     *
     * @return Query
     */
    public function whereNull($column, $connector = 'AND', $not = false)
    {
        $type = $not ? 'whereNotNull' : 'whereNull';

        $this->wheres[] = compact('type', 'column', 'connector');

        return $this;
    }

    /**
     * Add an or where not null condition to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * Add a where not null condition to the query.
     *
     * @param string $column
     * @param string $connector
     *
     * @return Query
     */
    public function whereNotNull($column, $connector = 'AND')
    {
        return $this->whereNull($column, $connector, true);
    }

    /**
     * Add a grouping to the query.
     *
     * @param string $column
     *
     * @return Query
     */
    public function groupBy($column)
    {
        $this->groupings[] = $column;

        return $this;
    }

    /**
     * Add a having to the query.
     *
     * @param string $column
     * @param string $operator
     *
     * @return Query
     */
    public function having($column, $operator, $value)
    {
        $this->havings[] = compact('column', 'operator', 'value');

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an ordering to the query.
     *
     * @param string $column
     * @param string $direction
     *
     * @return Query
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orderings[] = compact('column', 'direction');

        return $this;
    }

    /**
     * Find a record by the primary key.
     *
     * @param int $id
     *
     * @return object
     */
    public function find($id, array $columns = ['*'])
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @return object
     */
    public function findOrFail($id, array $columns = ['*'])
    {
        if (null !== ($model = $this->find($id, $columns))) {
            return $model;
        }

        throw new ModelNotFoundException();
    }

    /**
     * Execute the query as a SELECT statement and return the first result.
     */
    public function first(array $columns = ['*'])
    {
        $columns = (array)$columns;

        // Since we only need the first result, we'll go ahead and set the
        // limit clause to 1, since this will be much faster than getting
        // all of the rows and then only returning the first.
        $results = $this->take(1)->get($columns);

        return (\count($results) > 0) ? $results[0] : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(array $columns = ['*'])
    {
        if (null !== ($model = $this->first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException();
    }

    /**
     * Execute the query as a SELECT statement.
     *
     * @return array
     */
    public function get(array $columns = ['*'])
    {
        if (null === $this->selects) {
            $this->select($columns);
        }

        $sql = $this->grammar->select($this);

        $results = $this->connection->query($sql, $this->bindings);

        // If the query has an offset and we are using the SQL Server grammar,
        // we need to spin through the results and remove the "rownum" from
        // each of the objects since there is no "offset".
        if ($this->offset > 0 && $this->grammar instanceof SQLServerGrammar) {
            array_walk($results, static function ($result): void {
                $result->rownum = null;
            });
        }

        // Reset the SELECT clause so more queries can be performed using
        // the same instance. This is helpful for getting aggregates and
        // then getting actual results from the query.
        $this->selects = null;

        return $results;
    }

    /**
     * Pluck a single column from the database.
     *
     * @param string $column
     */
    public function pluck($column)
    {
        $result = $this->first([$column]);

        if ($result) {
            return $result->{$column};
        }
    }

    /**
     * Add an array of columns to the SELECT clause.
     *
     * @return Query
     */
    public function select(array $columns = ['*'])
    {
        $this->selects = (array)$columns;

        return $this;
    }

    /**
     * Set the query limit.
     *
     * @param int $value
     *
     * @return Query
     */
    public function take($value)
    {
        $this->limit = $value;

        return $this;
    }

    /**
     * Execute the query as a SELECT statement and return a single column.
     *
     * @param string $column
     */
    public function only($column)
    {
        $sql = $this->grammar->select($this->select([$column]));

        return $this->connection->only($sql, $this->bindings);
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param string $column
     * @param string $key
     *
     * @return array
     */
    public function lists($column, $key = null)
    {
        $columns = (null === $key) ? [$column] : [$column, $key];

        $results = $this->get($columns);

        // First we will get the array of values for the requested column.
        // Of course, this array will simply have numeric keys. After we
        // have this array we will determine if we need to key the array
        // by another column from the result set.
        $values = array_map(static fn ($row) => $row->$column, $results);

        // If a key was provided, we will extract an array of keys and
        // set the keys on the array of values using the array_combine
        // function provided by PHP, which should give us the proper
        // array form to return from the method.
        if (null !== $key && \count($results)) {
            return array_combine(array_map(static fn ($row) => $row->$key, $results), $values);
        }

        return $values;
    }

    /**
     * Get the paginated query results as a Paginator instance.
     *
     * @param int      $perPage
     * @param string   $pageName
     * @param int|null $page
     *
     * @return AbstractPaginator
     */
    public function paginate($perPage = 20, array $columns = ['*'], $pageName = 'page', $page = null)
    {
        // Because some database engines may throw errors if we leave orderings
        // on the query when retrieving the total number of records, we'll drop
        // all of the orderings and put them back on the query.
        // list($orderings, $this->orderings) = array($this->orderings, null);

        if (\is_array($this->selects) && \count($this->selects)) {
            $first_select = $this->grammar->columnize([$this->selects[0]]);
        } else {
            $first_select = $this->grammar->columnize([$columns[0]]);
        }

        $this->selects[0] = \DB::raw('SQL_CALC_FOUND_ROWS ' . $first_select);

        $page = \Paginator::getCurrentPage($pageName, $page);

        // $this->orderings = $orderings;

        $results = $this->forPage($page, $perPage)->get($columns);

        $total = $this->connection->query('SELECT FOUND_ROWS() as num_rows');

        $total = $total[0]->num_rows;

        if ($total > 0 && empty($results)) {
            // quick little fix if the page number is higher than the real number of pages
            $page = 1;
            // $results = $this->for_page($page, $per_page)->get($columns);
            // something like this, the above line wont work though because I don't think you can run ->get() twice on a query
        }

        return \Paginator::paginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => \Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int      $perPage
     * @param string   $pageName
     * @param int|null $page
     *
     * @return AbstractPaginator
     */
    public function simplePaginate($perPage = null, array $columns = ['*'], $pageName = 'page', $page = null)
    {
        // Because some database engines may throw errors if we leave orderings
        // on the query when retrieving the total number of records, we'll drop
        // all of the ordreings and put them back on the query.
        // list($orderings, $this->orderings) = array($this->orderings, null);

        if (\is_array($this->selects) && \count($this->selects)) {
            $first_select = $this->grammar->columnize([$this->selects[0]]);
        } else {
            $first_select = $this->grammar->columnize([$columns[0]]);
        }

        $this->selects[0] = \DB::raw('SQL_CALC_FOUND_ROWS ' . $first_select);

        if (!isset($page)) {
            $page = \Input::get('page', 1);
        }

        $page = $page >= 1 && false !== filter_var($page, \FILTER_VALIDATE_INT) ? $page : 1;

        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return \Paginator::simplePaginator(
            $this->get($columns),
            $perPage,
            $page,
            [
                'path' => \Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Set the query limit and offset for a given page.
     *
     * @param int $page
     * @param int $per_page
     *
     * @return Query
     */
    public function forPage($page, $per_page)
    {
        return $this->skip(($page - 1) * $per_page)->take($per_page);
    }

    /**
     * Chunk the results of the query.
     *
     * @param int $count
     */
    public function chunk($count, callable $callback): void
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (\count($results) > 0) {
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            \call_user_func($callback, $results);

            ++$page;

            $results = $this->forPage($page, $count)->get();
        }
    }

    /**
     * Set the query offset.
     *
     * @param int $value
     *
     * @return Query
     */
    public function skip($value)
    {
        $this->offset = $value;

        return $this;
    }

    /**
     * Insert an array of values into the database table.
     *
     * @return array
     */
    public function insert(array $values)
    {
        // Force every insert to be treated like a batch insert to make creating
        // the binding array simpler since we can just spin through the inserted
        // rows as if there/ was more than one every time.
        if (!\is_array(reset($values))) {
            $values = [$values];
        }

        $bindings = [];

        // We need to merge the the insert values into the array of the query
        // bindings so that they will be bound to the PDO statement when it
        // is executed by the database connection.
        foreach ($values as $value) {
            $bindings = array_merge($bindings, array_values($value));
        }

        $sql = $this->grammar->insert($this, $values);

        return $this->connection->query($sql, $bindings);
    }

    /**
     * Insert an array of values into the database table and return the key.
     *
     * @param string $column
     */
    public function insertGetId(array $values, $column = 'id')
    {
        $sql = $this->grammar->insertGetId($this, $values, $column);

        $result = $this->connection->query($sql, array_values($values));

        // If the key is not auto-incrementing, we will just return the inserted value
        if (isset($values[$column])) {
            return $values[$column];
        }

        if ($this->grammar instanceof PostgresGrammar) {
            $row = (array)$result[0];

            return (int)$row[$column];
        }

        return (int)$this->connection->lastInsertId();
    }

    /**
     * Increment the value of a column by a given amount.
     *
     * @param string $column
     * @param int    $amount
     *
     * @return array
     */
    public function increment($column, $amount = 1)
    {
        return $this->adjust($column, $amount, ' + ');
    }

    /**
     * Update an array of values in the database table.
     *
     * @param array $values
     *
     * @return array
     */
    public function update($values)
    {
        // For update statements, we need to merge the bindings such that the update
        // values occur before the where bindings in the array since the sets will
        // precede any of the where clauses in the SQL syntax that is generated.
        $bindings = array_merge(array_values($values), $this->bindings);

        $sql = $this->grammar->update($this, $values);

        return $this->connection->query($sql, $bindings);
    }

    /**
     * Decrement the value of a column by a given amount.
     *
     * @param string $column
     * @param int    $amount
     *
     * @return int
     */
    public function decrement($column, $amount = 1)
    {
        return $this->adjust($column, $amount, ' - ');
    }

    /**
     * Execute the query as a DELETE statement.
     *
     * Optionally, an ID may be passed to the method do delete a specific row.
     *
     * @param int $id
     *
     * @return array
     */
    public function delete($id = null)
    {
        // If an ID is given to the method, we'll set the where clause to
        // match on the value of the ID. This allows the developer to
        // quickly delete a row by its primary key value.
        if (null !== $id) {
            $this->where('id', '=', $id);
        }

        $sql = $this->grammar->delete($this);

        return $this->connection->query($sql, $this->bindings);
    }

    /**
     * Get an aggregate value.
     *
     * @param string $aggregator
     */
    public function aggregate($aggregator, array $columns)
    {
        // We'll set the aggregate value so the grammar does not try to compile
        // a SELECT clause on the query. If an aggregator is present, it's own
        // grammar function will be used to build the SQL syntax.
        if (!$this->groupings) {
            $this->aggregate = compact('aggregator', 'columns');
            $sql = $this->grammar->select($this);
        } else {
            if (null === $this->selects) {
                $this->select(['*']);
            }
            $sql = "SELECT {$aggregator}({$this->grammar->columnize($columns)}) FROM ({$this->grammar->select($this)}) AS aggregate";
        }
        $result = $this->connection->only($sql, $this->bindings);
        // Reset the aggregate so more queries can be performed using the same
        // instance. This is helpful for getting aggregates and then getting
        // actual results from the query such as during paging.
        $this->aggregate = null;

        return $result;
    }

    /**
     * Adjust the value of a column up or down by a given amount.
     *
     * @param string $column
     * @param int    $amount
     * @param string $operator
     *
     * @return array
     */
    protected function adjust($column, $amount, $operator)
    {
        $wrapped = $this->grammar->wrap($column);

        // To make the adjustment to the column, we'll wrap the expression in an
        // Expression instance, which forces the adjustment to be injected into
        // the query as a string instead of bound.
        $value = Database::raw($wrapped . $operator . $amount);

        return $this->update([$column => $value]);
    }

    /**
     * Add dynamic where conditions to the query.
     *
     * @param string $method
     *
     * @return Query
     */
    private function dynamicWhere($method, array $parameters)
    {
        $finder = mb_substr($method, mb_strlen('where'));

        $segments = preg_split(
            '/(And|Or)(?=[A-Z])/',
            $finder,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE
        );

        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'AND';

        $index = 0;

        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ('And' !== $segment && 'Or' !== $segment) {
                // Once we have parsed out the columns and formatted the boolean operators we
                // are ready to add it to this query as a where clause just like any other
                // clause on the query. Then we'll increment the parameter index values.
                $this->where(Str::snake($segment), '=', $parameters[$index], mb_strtoupper($connector));

                ++$index;
            } else {
                // Otherwise, we will store the connector so we know how the next where clause we
                // find in the query should be connected to the previous ones, meaning we will
                // have the proper boolean connector to connect the next where clause found.
                $connector = $segment;
            }
        }

        return $this;
    }
}
