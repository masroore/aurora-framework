<?php

namespace Aurora\Database\Eloquent\Relationships;

use Aurora\Database\Eloquent\Model;
use Aurora\Database\Eloquent\Pivot;

class HasManyAndBelongsTo extends Relationship
{
    /**
     * The name of the intermediate, joining table.
     *
     * @var string
     */
    protected $joining;

    /**
     * The other or "associated" key. This is the foreign key of the related model.
     *
     * @var string
     */
    protected $other;

    /**
     * The columns on the joining table that should be fetched.
     *
     * @var array
     */
    protected $with = ['id'];

    /**
     * Create a new many to many relationship instance.
     *
     * @param Model  $model
     * @param string $associated
     * @param string $table
     * @param string $foreign
     * @param string $other
     */
    public function __construct($model, $associated, $table, $foreign, $other)
    {
        $this->other = $other;

        $this->joining = $table ?: $this->joining($model, $associated);

        // If the Pivot table is timestamped, we'll set the timestamp columns to be
        // fetched when the pivot table models are fetched by the developer else
        // the ID will be the only "extra" column fetched in by default.
        if (Pivot::$timestamps) {
            $this->with[] = 'created_at';
            $this->with[] = 'updated_at';
        }

        parent::__construct($model, $associated, $foreign);
    }

    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return array
     */
    public function results()
    {
        return parent::get();
    }

    /**
     * Sync the joining table with the array of given IDs.
     *
     * @param array $ids
     */
    public function sync($ids): void
    {
        $current = $this->pivot()->lists($this->other_key());
        $ids = (array)$ids;

        // First we need to attach any of the associated models that are not currently
        // in the joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we insert.
        foreach ($ids as $id) {
            if (!\in_array($id, $current, true)) {
                $this->attach($id);
            }
        }

        // Next we will take the difference of the current and given IDs and detach
        // all of the entities that exists in the current array but are not in
        // the array of IDs given to the method, finishing the sync.
        $detach = array_diff($current, $ids);

        if (\count($detach) > 0) {
            $this->detach($detach);
        }
    }

    /**
     * Get a relationship instance of the pivot table.
     *
     * @return HasMany
     */
    public function pivot()
    {
        $pivot = new Pivot($this->joining, $this->model->connection());

        return new HasMany($this->base, $pivot, $this->foreignKey());
    }

    /**
     * Insert a new record into the joining table of the association.
     *
     * @param int|Model $id
     * @param array     $attributes
     *
     * @return bool
     */
    public function attach($id, $attributes = [])
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $joining = array_merge($this->join_record($id), $attributes);

        return $this->insert_joining($joining);
    }

    /**
     * Detach a record from the joining table of the association.
     *
     * @param array|int|Model $ids
     *
     * @return bool
     */
    public function detach($ids)
    {
        if ($ids instanceof Model) {
            $ids = [$ids->getKey()];
        } elseif (!\is_array($ids)) {
            $ids = [$ids];
        }

        return $this->pivot()->whereIn($this->other_key(), $ids)->delete();
    }

    /**
     * Insert a new record for the association.
     *
     * @param array|Model $attributes
     * @param array       $joining
     *
     * @return bool
     */
    public function insert($attributes, $joining = [])
    {
        // If the attributes are actually an instance of a model, we'll just grab the
        // array of attributes off of the model for saving, allowing the developer
        // to easily validate the joining models before inserting them.
        if ($attributes instanceof Model) {
            $attributes = $attributes->attributes;
        }

        $model = $this->model->create($attributes);

        // If the insert was successful, we'll insert a record into the joining table
        // using the new ID that was just inserted into the related table, allowing
        // the developer to not worry about maintaining the join table.
        if ($model instanceof Model) {
            $joining = array_merge($this->join_record($model->getKey()), $joining);

            $result = $this->insert_joining($joining);
        }

        return $model instanceof Model && $result;
    }

    /**
     * Delete all of the records from the joining table for the model.
     *
     * @return int
     */
    public function delete()
    {
        return $this->pivot()->delete();
    }

    /**
     * Initialize a relationship on an array of parent models.
     *
     * @param array  $parents
     * @param string $relationship
     */
    public function initialize(&$parents, $relationship): void
    {
        foreach ($parents as &$parent) {
            $parent->relationships[$relationship] = [];
        }
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param array $results
     */
    public function eagerlyConstrain($results): void
    {
        $this->table->whereIn($this->joining . '.' . $this->foreignKey(), $this->keys($results));
    }

    /**
     * Match eagerly loaded child models to their parent models.
     *
     * @param array $parents
     * @param array $children
     */
    public function match($relationship, &$parents, $children): void
    {
        $foreign = $this->foreignKey();

        $dictionary = [];

        foreach ($children as $child) {
            $dictionary[$child->pivot->$foreign][] = $child;
        }

        foreach ($parents as $parent) {
            if (\array_key_exists($key = $parent->get_key(), $dictionary)) {
                $parent->relationships[$relationship] = $dictionary[$key];
            }
        }
    }

    /**
     * Set the columns on the joining table that should be fetched.
     *
     * @return Relationship
     */
    public function with($columns)
    {
        $columns = (\is_array($columns)) ? $columns : \func_get_args();

        // The "with" array contains a couple of columns by default, so we will just
        // merge in the developer specified columns here, and we will make sure
        // the values of the array are unique to avoid duplicates.
        $this->with = array_unique(array_merge($this->with, $columns));

        $this->set_select($this->foreignKey(), $this->other_key());

        return $this;
    }

    /**
     * Determine the joining table name for the relationship.
     *
     * By default, the name is the models sorted and joined with underscores.
     *
     * @return string
     */
    protected function joining($model, $associated)
    {
        $models = [class_basename($model), class_basename($associated)];

        sort($models);

        return mb_strtolower($models[0] . '_' . $models[1]);
    }

    /**
     * Get the other or associated key for the relationship.
     *
     * @return string
     */
    protected function other_key()
    {
        return Relationship::foreign($this->model, $this->other);
    }

    /**
     * Create an array representing a new joining record for the association.
     *
     * @param int $id
     *
     * @return array
     */
    protected function join_record($id)
    {
        return [$this->foreignKey() => $this->base->getKey(), $this->other_key() => $id];
    }

    /**
     * Insert a new record into the joining table of the association.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function insert_joining($attributes)
    {
        if (Pivot::$timestamps) {
            $attributes['created_at'] = new \DateTime();

            $attributes['updated_at'] = $attributes['created_at'];
        }

        return $this->joiningTable()->insert($attributes);
    }

    /**
     * Get a fluent query for the joining table of the relationship.
     *
     * @return \Aurora\Database\Query
     */
    protected function joiningTable()
    {
        return $this->connection()->table($this->joining);
    }

    /**
     * Set the proper constraints on the relationship table.
     */
    protected function constrain(): void
    {
        $other = $this->other_key();

        $foreign = $this->foreignKey();

        $this->set_select($foreign, $other)->set_join($other)->set_where($foreign);
    }

    /**
     * Set the SELECT clause on the query builder for the relationship.
     *
     * @param string $foreign
     * @param string $other
     *
     * @return HasManyAndBelongsTo
     */
    protected function set_select($foreign, $other)
    {
        $columns = [$this->model->table() . '.*'];

        $this->with = array_merge($this->with, [$foreign, $other]);

        // Since pivot tables may have extra information on them that the developer
        // needs we allow an extra array of columns to be specified that will be
        // fetched from the pivot table and hydrate into the pivot model.
        foreach ($this->with as $column) {
            $columns[] = $this->joining . '.' . $column . ' as pivot_' . $column;
        }

        $this->table->select($columns);

        return $this;
    }

    /**
     * Set the JOIN clause on the query builder for the relationship.
     *
     * @param string $other
     *
     * @return HasManyAndBelongsTo
     */
    protected function set_join($other)
    {
        $this->table->join($this->joining, $this->associated_key(), '=', $this->joining . '.' . $other);

        return $this;
    }

    /**
     * Get the fully qualified associated table's primary key.
     *
     * @return string
     */
    protected function associated_key()
    {
        return $this->model->table() . '.' . $this->model->key();
    }

    /**
     * Set the WHERE clause on the query builder for the relationship.
     *
     * @param string $foreign
     *
     * @return HasManyAndBelongsTo
     */
    protected function set_where($foreign)
    {
        $this->table->where($this->joining . '.' . $foreign, '=', $this->base->getKey());

        return $this;
    }

    /**
     * Hydrate the Pivot model on an array of results.
     *
     * @param array $results
     */
    protected function hydratePivot(&$results): void
    {
        foreach ($results as &$result) {
            // Every model result for a many-to-many relationship needs a Pivot instance
            // to represent the pivot table's columns. Sometimes extra columns are on
            // the pivot table that may need to be accessed by the developer.
            $pivot = new Pivot($this->joining, $this->model->connection());

            // If the attribute key starts with "pivot_", we know this is a column on
            // the pivot table, so we will move it to the Pivot model and purge it
            // from the model since it actually belongs to the pivot model.
            foreach ($result->attributes as $key => $value) {
                if (Str::startsWith($key, 'pivot_')) {
                    $pivot->{mb_substr($key, 6)} = $value;

                    $result->purge($key);
                }
            }

            // Once we have completed hydrating the pivot model instance, we'll set
            // it on the result model's relationships array so the developer can
            // quickly and easily access any pivot table information.
            $result->relationships['pivot'] = $pivot;

            $pivot->sync() && $result->sync();
        }
    }
}
