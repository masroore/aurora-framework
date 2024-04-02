<?php

namespace Aurora\Database\Eloquent;

use Aurora\Collection;
use Aurora\Database;
use Aurora\Database\ModelNotFoundException;
use Aurora\Pagination\AbstractPaginator;
use Aurora\Paginator;

class Query
{
    /**
     * The model instance being queried.
     *
     * @var Model
     */
    public $model;

    /**
     * The fluent query builder for the query instance.
     *
     * @var Query
     */
    public $table;

    /**
     * The relationships that should be eagerly loaded by the query.
     *
     * @var array
     */
    public $includes = [];

    /**
     * The methods that should be returned from the fluent query builder.
     *
     * @var array
     */
    public $passthru = [
        'lists', 'only', 'insert', 'insertGetId', 'update', 'increment',
        'delete', 'decrement', 'count', 'min', 'max', 'avg', 'sum',
    ];

    /**
     * Creat a new query instance for a model.
     *
     * @param Model $model
     */
    public function __construct($model)
    {
        $this->model = ($model instanceof Model) ? $model : new $model();

        $this->table = $this->table();
    }

    /**
     * Handle dynamic method calls to the query.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __call($method, $parameters)
    {
        $result = \call_user_func_array([$this->table, $method], $parameters);

        // Some methods may get their results straight from the fluent query
        // builder such as the aggregate methods. If the called method is
        // one of these, we will just return the result straight away.
        if (\in_array($method, $this->passthru, true)) {
            return $result;
        }

        return $this;
    }

    /**
     * Get the database connection for the model.
     *
     * @return Database\Connection
     */
    public function connection()
    {
        return Database::connection($this->model->connection());
    }

    /**
     * Find a model by its primary key.
     *
     * @param array $columns
     */
    public function find($id, $columns = ['*'])
    {
        $model = $this->model;

        $this->table->where($model::$key, '=', $id);

        return $this->first($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param array $columns
     *
     * @return Model|static
     */
    public function findOrFail($id, $columns = ['*'])
    {
        if (null !== ($model = $this->find($id, $columns))) {
            return $model;
        }

        throw new ModelNotFoundException();
    }

    /**
     * Get the first model result for the query.
     *
     * @param array $columns
     */
    public function first($columns = ['*'])
    {
        $results = $this->hydrate($this->model, $this->table->take(1)->get($columns));

        return (\count($results) > 0) ? head($results) : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @return Model|static
     */
    public function firstOrFail(array $columns = ['*'])
    {
        if (null !== ($model = $this->first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException();
    }

    /**
     * Hydrate an array of models from the given results.
     *
     * @param Model            $model
     * @param array|Collection $results
     *
     * @return array
     */
    public function hydrate($model, $results)
    {
        $class = $model::class;

        $models = [];

        // We'll spin through the array of database results and hydrate a model
        // for each one of the records. We will also set the "exists" flag to
        // "true" so that the model will be updated when it is saved.
        foreach ((array)$results as $result) {
            $result = (array)$result;

            $new = new $class([], true);

            // We need to set the attributes manually in case the accessible property is
            // set on the array which will prevent the mass assignemnt of attributes if
            // we were to pass them in using the constructor or fill methods.
            $new->fillRaw($result);

            $models[] = $new;
        }

        if (\count($results) > 0) {
            foreach ($this->model_includes() as $relationship => $constraints) {
                // If the relationship is nested, we will skip loading it here and let
                // the load method parse and set the nested eager loads on the right
                // relationship when it is getting ready to eager load.
                if (str_contains($relationship, '.')) {
                    continue;
                }

                $this->load($models, $relationship, $constraints);
            }
        }

        // The many to many relationships may have pivot table column on them
        // so we will call the "clean" method on the relationship to remove
        // any pivot columns that are on the model.
        if ($this instanceof Relationships\HasManyAndBelongsTo) {
            $this->hydratePivot($models);
        }

        return $models;
    }

    /**
     * Get all of the model results for the query.
     *
     * @return array
     */
    public function get(array $columns = ['*'])
    {
        return $this->hydrate($this->model, $this->table->get($columns));
    }

    /**
     * Get an array of paginated model results.
     *
     * @param int      $perPage
     * @param string   $pageName
     * @param int|null $page
     *
     * @return AbstractPaginator
     */
    public function paginate($perPage = null, array $columns = ['*'], $pageName = 'page', $page = null)
    {
        $perPage = $perPage ?: $this->model->perPage();

        // First we'll grab the Paginator instance and get the results. Then we can
        // feed those raw database results into the hydrate method to get models
        // for the results, which we'll set on the paginator and return it.
        $paginator = $this->table->paginate($perPage, $columns, $pageName, $page);

        $paginator->setCollection(Collection::make($this->hydrate($this->model, $paginator->items())));

        return $paginator;
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param int|null $page
     *
     * @return AbstractPaginator
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->perPage();

        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return Paginator::simplePaginator($this->get($columns), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get a fluent query builder for the model.
     *
     * @return Database\Query
     */
    protected function table()
    {
        return $this->connection()->table($this->model->table());
    }

    /**
     * Get the eagerly loaded relationships for the model.
     *
     * @return array
     */
    protected function model_includes()
    {
        $includes = [];

        foreach ($this->model->includes as $relationship => $constraints) {
            // When eager loading relationships, constraints may be set on the eager
            // load definition; however, is none are set, we need to swap the key
            // and the value of the array since there are no constraints.
            if (is_numeric($relationship)) {
                [$relationship, $constraints] = [$constraints, null];
            }

            $includes[$relationship] = $constraints;
        }

        return $includes;
    }

    /**
     * Hydrate an eagerly loaded relationship on the model results.
     *
     * @param array      $results
     * @param string     $relationship
     * @param array|null $constraints
     */
    protected function load(&$results, $relationship, $constraints): void
    {
        $query = $this->model->$relationship();

        $query->model->includes = $this->nestedIncludes($relationship);

        // We'll remove any of the where clauses from the relationship to give
        // the relationship the opportunity to set the constraints for an
        // eager relationship using a separate, specific method.
        $query->table->reset_where();

        $query->eagerlyConstrain($results);

        // Constraints may be specified in-line for the eager load by passing
        // a Closure as the value portion of the eager load. We can use the
        // query builder's nested query support to add the constraints.
        if (null !== $constraints) {
            $query->table->whereNested($constraints);
        }

        $query->initialize($results, $relationship);

        $query->match($relationship, $results, $query->get());
    }

    /**
     * Gather the nested includes for a given relationship.
     *
     * @param string $relationship
     *
     * @return array
     */
    protected function nestedIncludes($relationship)
    {
        $nested = [];

        foreach ($this->model_includes() as $include => $constraints) {
            // To get the nested includes, we want to find any includes that begin
            // the relationship and a dot, then we will strip off the leading
            // nesting indicator and set the include in the array.
            if (Str::startsWith($include, $relationship . '.')) {
                $nested[mb_substr($include, mb_strlen($relationship . '.'))] = $constraints;
            }
        }

        return $nested;
    }
}
