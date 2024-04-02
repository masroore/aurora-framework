<?php

namespace Aurora\Database\Eloquent\Relationships;

use Aurora\Database\Eloquent\Model;

class HasOneOrMany extends Relationship
{
    /**
     * Insert a new record for the association.
     *
     * If save is successful, the model will be returned, otherwise false.
     *
     * @param array|Model $attributes
     *
     * @return false|Model
     */
    public function insert($attributes)
    {
        if ($attributes instanceof Model) {
            $attributes->setAttribute($this->foreignKey(), $this->base->getKey());

            return $attributes->save() ? $attributes : false;
        }

        $attributes[$this->foreignKey()] = $this->base->getKey();

        return $this->model->create($attributes);
    }

    /**
     * Update a record for the association.
     *
     * @return bool
     */
    public function update(array $attributes)
    {
        if ($this->model->timestamps()) {
            $attributes['updated_at'] = new \DateTime();
        }

        return $this->table->update($attributes);
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param array $results
     */
    public function eagerlyConstrain($results): void
    {
        $this->table->whereIn($this->foreignKey(), $this->keys($results));
    }

    /**
     * Set the proper constraints on the relationship table.
     */
    protected function constrain(): void
    {
        $this->table->where($this->foreignKey(), '=', $this->base->getKey());
    }
}
