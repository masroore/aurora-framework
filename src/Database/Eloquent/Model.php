<?php

namespace Aurora\Database\Eloquent;

use Aurora\Database\Eloquent\Relationships\BelongsTo;
use Aurora\Database\Eloquent\Relationships\HasMany;
use Aurora\Database\Eloquent\Relationships\HasManyAndBelongsTo;
use Aurora\Database\Eloquent\Relationships\HasOne;
use Aurora\Event;
use Aurora\Str;

abstract class Model
{
    /**
     * The primary key for the model on the database table.
     *
     * @var string
     */
    public static $key = 'id';

    /**
     * The attributes that are accessible for mass assignment.
     *
     * @var array
     */
    public static $accessible;

    /**
     * The attributes that should be excluded from to_array.
     *
     * @var array
     */
    public static $hidden = [];

    /**
     * Indicates if the model has update and creation timestamps.
     *
     * @var bool
     */
    public static $timestamps = true;

    /**
     * The name of the table associated with the model.
     *
     * @var string
     */
    public static $table;

    /**
     * The name of the database connection that should be used for the model.
     *
     * @var string
     */
    public static $connection;

    /**
     * The name of the sequence associated with the model.
     *
     * @var string
     */
    public static $sequence;

    /**
     * The default number of models to show per page when paginating.
     *
     * @var int
     */
    public static $perPage = 20;

    /**
     * All of the model's attributes.
     *
     * @var array
     */
    public $attributes = [];

    /**
     * The model's attributes in their original state.
     *
     * @var array
     */
    public $original = [];

    /**
     * The relationships that have been loaded for the query.
     *
     * @var array
     */
    public $relationships = [];

    /**
     * Indicates if the model exists in the database.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The relationships that should be eagerly loaded.
     *
     * @var array
     */
    public $includes = [];

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     * @param bool  $exists
     */
    public function __construct($attributes = [], $exists = false)
    {
        $this->exists = $exists;

        $this->fill($attributes);
    }

    /**
     * Dynamically handle static method calls on the model.
     *
     * @param string $method
     */
    public static function __callStatic($method, array $parameters)
    {
        $model = static::class;

        return \call_user_func_array([new $model(), $method], $parameters);
    }

    /**
     * Handle the dynamic retrieval of attributes and associations.
     *
     * @param string $key
     */
    public function __get($key)
    {
        // First we will check to see if the requested key is an already loaded
        // relationship and return it if it is. All relationships are stored
        // in the special relationships array so they are not persisted.
        if (\array_key_exists($key, $this->relationships)) {
            return $this->relationships[$key];
        }

        // Next we'll check if the requested key is in the array of attributes
        // for the model. These are simply regular properties that typically
        // correspond to a single column on the database for the model.
        if (\array_key_exists($key, $this->attributes)) {
            return $this->{"get_{$key}"}();
        }

        // If the item is not a loaded relationship, it may be a relationship
        // that hasn't been loaded yet. If it is, we will lazy load it and
        // set the value of the relationship in the relationship array.
        if (method_exists($this, $key)) {
            return $this->relationships[$key] = $this->$key()->results();
        }

        // Finally we will just assume the requested key is just a regular
        // attribute and attempt to call the getter method for it, which
        // will fall into the __call method if one doesn't exist.
        return $this->{"get_{$key}"}();
    }

    /**
     * Handle the dynamic setting of attributes.
     *
     * @param string $key
     */
    public function __set($key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        foreach (['attributes', 'relationships'] as $source) {
            if (\array_key_exists($key, $this->{$source})) {
                return !empty($this->{$source}[$key]);
            }
        }

        return false;
    }

    /**
     * Remove an attribute from the model.
     *
     * @param string $key
     */
    public function __unset($key): void
    {
        foreach (['attributes', 'relationships'] as $source) {
            unset($this->{$source}[$key]);
        }
    }

    /**
     * Handle dynamic method calls on the model.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __call($method, $parameters)
    {
        $meta = ['key', 'table', 'connection', 'sequence', 'per_page', 'timestamps'];

        // If the method is actually the name of a static property on the model we'll
        // return the value of the static property. This makes it convenient for
        // relationships to access these values off of the instances.
        if (\in_array($method, $meta, true)) {
            return static::$$method;
        }

        $underscored = ['with', 'query'];

        // Some methods need to be accessed both staticly and non-staticly so we'll
        // keep underscored methods of those methods and intercept calls to them
        // here so they can be called either way on the model instance.
        if (\in_array($method, $underscored, true)) {
            return \call_user_func_array([$this, '_' . $method], $parameters);
        }

        // First we want to see if the method is a getter / setter for an attribute.
        // If it is, we'll call the basic getter and setter method for the model
        // to perform the appropriate action based on the method.
        if (Str::startsWith($method, 'get_')) {
            return $this->getAttribute(mb_substr($method, 4));
        }

        if (Str::startsWith($method, 'set_')) {
            return $this->setAttribute(mb_substr($method, 4), $parameters[0]);
        }

        // Finally we will assume that the method is actually the beginning of a
        // query, such as "where", and will create a new query instance and
        // call the method on the query instance, returning it after.
        return \call_user_func_array([$this->query(), $method], $parameters);
    }

    /**
     * Hydrate the model with an array of attributes.
     *
     * @param bool $raw
     *
     * @return Model
     */
    public function fill(array $attributes, $raw = false)
    {
        foreach ($attributes as $key => $value) {
            // If the "raw" flag is set, it means that we'll just load every value from
            // the array directly into the attributes, without any accessibility or
            // mutators being accounted for. What you pass in is what you get.
            if ($raw) {
                $this->setAttribute($key, $value);

                continue;
            }

            // If the "accessible" property is an array, the developer is limiting the
            // attributes that may be mass assigned, and we need to verify that the
            // current attribute is included in that list of allowed attributes.
            if (\is_array(static::$accessible)) {
                if (\in_array($key, static::$accessible, true)) {
                    $this->$key = $value;
                }
            }

            // If the "accessible" property is not an array, no attributes have been
            // white-listed and we are free to set the value of the attribute to
            // the value that has been passed into the method without a check.
            else {
                $this->$key = $value;
            }
        }

        // If the original attribute values have not been set, we will set
        // them to the values passed to this method allowing us to easily
        // check if the model has changed since hydration.
        if (0 === \count($this->original)) {
            $this->original = $this->attributes;
        }

        return $this;
    }

    /**
     * Set an attribute's value on the model.
     *
     * @param string $key
     *
     * @return Model
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set the accessible attributes for the given model.
     *
     * @param array $attributes
     *
     * @return array
     */
    public static function accessible($attributes = null)
    {
        if (null === $attributes) {
            return static::$accessible;
        }

        static::$accessible = $attributes;
    }

    /**
     * Create a new model and store it in the database.
     *
     * If save is successful, the model will be returned, otherwise false.
     *
     * @param array $attributes
     *
     * @return false|Model
     */
    public static function create($attributes)
    {
        $model = new static($attributes);

        $success = $model->save();

        return ($success) ? $model : false;
    }

    /**
     * Save the model instance to the database.
     *
     * @return bool
     */
    public function save()
    {
        if (!$this->dirty()) {
            return true;
        }

        if (static::$timestamps) {
            $this->timestamp();
        }

        $this->fireEvent('saving');

        // If the model exists, we only need to update it in the database, and the update
        // will be considered successful if there is one affected row returned from the
        // fluent query instance. We'll set the where condition automatically.
        if ($this->exists) {
            $query = $this->query()->where(static::$key, '=', $this->getKey());

            $result = 1 === $query->update($this->getDirty());

            if ($result) {
                $this->fireEvent('updated');
            }
        }

        // If the model does not exist, we will insert the record and retrieve the last
        // insert ID that is associated with the model. If the ID returned is numeric
        // then we can consider the insert successful.
        else {
            $id = $this->query()->insertGetId($this->attributes, $this->key());

            $this->setKey($id);

            $this->exists = $result = is_numeric($this->getKey());

            if ($result) {
                $this->fireEvent('created');
            }
        }

        // After the model has been "saved", we will set the original attributes to
        // match the current attributes so the model will not be viewed as being
        // dirty and subsequent calls won't hit the database.
        $this->original = $this->attributes;

        if ($result) {
            $this->fireEvent('saved');
        }

        return $result;
    }

    /**
     * Determine if the model has been changed from its original state.
     *
     * Models that haven't been persisted to storage are always considered dirty.
     *
     * @return bool
     */
    public function dirty()
    {
        return !$this->exists || \count($this->getDirty()) > 0;
    }

    /**
     * Get the dirty attributes for the model.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!\array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Set the update and creation timestamps on the model.
     */
    public function timestamp(): void
    {
        $this->updated_at = new \DateTime();

        if (!$this->exists) {
            $this->created_at = $this->updated_at;
        }
    }

    /**
     * Get the value of the primary key for the model.
     *
     * @return int
     */
    public function getKey()
    {
        return array_get($this->attributes, static::$key);
    }

    /**
     * Set the value of the primary key for the model.
     *
     * @param int $value
     *
     * @return Model
     */
    public function setKey($value)
    {
        return $this->setAttribute(static::$key, $value);
    }

    /**
     * Update a model instance in the database.
     *
     * @param array $attributes
     *
     * @return int
     */
    public static function update($id, $attributes)
    {
        $model = new static([], true);

        $model->fill($attributes);

        if (static::$timestamps) {
            $model->timestamp();
        }

        return $model->query()->where($model->key(), '=', $id)->update($model->attributes);
    }

    /**
     * Get all of the models in the database.
     *
     * @return array
     */
    public static function all()
    {
        return with(new static())->query()->get();
    }

    /**
     * Fill the model with the contents of the array.
     *
     * No mutators or accessibility checks will be accounted for.
     *
     * @return Model
     */
    public function fillRaw(array $attributes)
    {
        return $this->fill($attributes, true);
    }

    /**
     * The relationships that should be eagerly loaded by the query.
     *
     * @param array $includes
     *
     * @return Model
     */
    public function _with($includes)
    {
        $this->includes = (array)$includes;

        return $this;
    }

    /**
     * Get the query for a one-to-one association.
     *
     * @param string $model
     * @param string $foreign
     *
     * @return HasMany|HasOne
     */
    public function hasOne($model, $foreign = null)
    {
        return $this->hasOneOrMany(__FUNCTION__, $model, $foreign);
    }

    /**
     * Get the query for a one-to-many association.
     *
     * @param string $model
     * @param string $foreign
     *
     * @return HasMany|HasOne
     */
    public function hasMany($model, $foreign = null)
    {
        return $this->hasOneOrMany(__FUNCTION__, $model, $foreign);
    }

    /**
     * Get the query for a one-to-one (inverse) relationship.
     *
     * @param string $model
     * @param string $foreign
     *
     * @return BelongsTo
     */
    public function belongsTo($model, $foreign = null)
    {
        // If no foreign key is specified for the relationship, we will assume that the
        // name of the calling function matches the foreign key. For example, if the
        // calling function is "manager", we'll assume the key is "manager_id".
        if (null === $foreign) {
            [, $caller] = debug_backtrace(false);

            $foreign = "{$caller['function']}_id";
        }

        return new BelongsTo($this, $model, $foreign);
    }

    /**
     * Get the query for a many-to-many relationship.
     *
     * @param string $model
     * @param string $table
     * @param string $foreign
     * @param string $other
     *
     * @return HasManyAndBelongsTo
     */
    public function hasManyAndBelongsTo($model, $table = null, $foreign = null, $other = null)
    {
        return new HasManyAndBelongsTo($this, $model, $table, $foreign, $other);
    }

    /**
     * Save the model and all of its relations to the database.
     *
     * @return bool
     */
    public function push()
    {
        if (!$this->save()) {
            return false;
        }

        // To sync all of the relationships to the database, we will simply spin through
        // the relationships, calling the "push" method on each of the models in that
        // given relationship, this should ensure that each model is saved.
        foreach ($this->relationships as $name => $models) {
            if (!\is_array($models)) {
                $models = [$models];
            }

            foreach ($models as $model) {
                if (!$model->push()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Delete the model from the database.
     *
     * @return int
     */
    public function delete()
    {
        if ($this->exists) {
            $this->fireEvent('deleting');

            $result = $this->query()->where(static::$key, '=', $this->getKey())->delete();

            $this->fireEvent('deleted');

            return $result;
        }
    }

    /**
     * Updates the timestamp on the model and immediately saves it.
     */
    public function touch(): void
    {
        $this->timestamp();
        $this->save();
    }

    /**
     * Sync the original attributes with the current attributes.
     *
     * @return bool
     */
    final public function sync()
    {
        $this->original = $this->attributes;

        return true;
    }

    /**
     * Determine if a given attribute has changed from its original state.
     *
     * @param string $attribute
     *
     * @return bool
     */
    public function changed($attribute)
    {
        return array_get($this->attributes, $attribute) !== array_get($this->original, $attribute);
    }

    /**
     * Get the name of the table associated with the model.
     *
     * @return string
     */
    public function table()
    {
        return static::$table ?: mb_strtolower(Str::plural(class_basename($this)));
    }

    /**
     * Remove an attribute from the model.
     *
     * @param string $key
     */
    final public function purge($key): void
    {
        unset($this->original[$key], $this->attributes[$key]);
    }

    /**
     * Get the model attributes and relationships in array form.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = [];

        // First we need to gather all of the regular attributes. If the attribute
        // exists in the array of "hidden" attributes, it will not be added to
        // the array so we can easily exclude things like passwords, etc.
        foreach (array_keys($this->attributes) as $attribute) {
            if (!\in_array($attribute, static::$hidden, true)) {
                $attributes[$attribute] = $this->$attribute;
            }
        }

        foreach ($this->relationships as $name => $models) {
            // Relationships can be marked as "hidden", too.
            if (\in_array($name, static::$hidden, true)) {
                continue;
            }

            // If the relationship is not a "to-many" relationship, we can just
            // toArray the related model and add it as an attribute to the
            // array of existing regular attributes we gathered.
            if ($models instanceof self) {
                $attributes[$name] = $models->toArray();
            }

            // If the relationship is a "to-many" relationship we need to spin
            // through each of the related models and add each one with the
            // toArray method, keying them both by name and ID.
            elseif (\is_array($models)) {
                $attributes[$name] = [];

                foreach ($models as $id => $model) {
                    $attributes[$name][$id] = $model->toArray();
                }
            } elseif (null === $models) {
                $attributes[$name] = $models;
            }
        }

        return $attributes;
    }

    /**
     * Get a given attribute from the model.
     *
     * @param string $key
     */
    public function getAttribute($key)
    {
        return array_get($this->attributes, $key);
    }

    /**
     * Fire a given event for the model.
     *
     * @param string $event
     */
    protected function fireEvent($event): void
    {
        $events = ["eloquent.{$event}", "eloquent.{$event}: " . static::class];

        Event::fire($events, [$this]);
    }

    /**
     * Get the query for a one-to-one / many association.
     *
     * @param string $type
     * @param string $model
     * @param string $foreign
     *
     * @return HasMany|HasOne
     */
    protected function hasOneOrMany($type, $model, $foreign)
    {
        if ('has_one' === $type) {
            return new HasOne($this, $model, $foreign);
        }

        return new HasMany($this, $model, $foreign);
    }

    /**
     * Get a new fluent query builder instance for the model.
     *
     * @return Query
     */
    protected function _query()
    {
        return new Query($this);
    }

    /**
     * Get a new fluent query builder instance for the model.
     *
     * @return Query
     */
    protected function newQuery()
    {
        return $this->_query();
    }
}
