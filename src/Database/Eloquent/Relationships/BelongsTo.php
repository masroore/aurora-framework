<?php

namespace Aurora\Database\Eloquent\Relationships;

use Aurora\Database\Eloquent\Model;

class BelongsTo extends Relationship
{
    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return Model
     */
    public function results()
    {
        return parent::first();
    }

    /**
     * Update the parent model of the relationship.
     *
     * @param array|Model $attributes
     *
     * @return int
     */
    public function update($attributes)
    {
        $attributes = ($attributes instanceof Model) ? $attributes->getDirty() : $attributes;

        return $this->model->update($this->foreignValue(), $attributes);
    }

    /**
     * Get the value of the foreign key from the base model.
     */
    public function foreignValue()
    {
        return $this->base->{$this->foreign};
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
            $parent->relationships[$relationship] = null;
        }
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param array $results
     */
    public function eagerlyConstrain($results): void
    {
        $keys = [];

        // Inverse one-to-many relationships require us to gather the keys from the
        // parent models and use those keys when setting the constraint since we
        // are looking for the parent of a child model in this relationship.
        foreach ($results as $result) {
            if (null !== ($key = $result->{$this->foreignKey()})) {
                $keys[] = $key;
            }
        }

        if (0 === \count($keys)) {
            $keys = [0];
        }

        $this->table->whereIn($this->model->key(), array_unique($keys));
    }

    /**
     * Match eagerly loaded child models to their parent models.
     *
     * @param array $children
     * @param array $parents
     */
    public function match($relationship, &$children, $parents): void
    {
        $foreign = $this->foreignKey();

        $dictionary = [];

        foreach ($parents as $parent) {
            $dictionary[$parent->get_key()] = $parent;
        }

        foreach ($children as $child) {
            if (\array_key_exists($child->$foreign, $dictionary)) {
                $child->relationships[$relationship] = $dictionary[$child->$foreign];
            }
        }
    }

    /**
     * Bind an object over a belongs-to relation using its id.
     *
     * @return Model
     */
    public function bind($id)
    {
        $this->base->fill([$this->foreign => $id])->save();

        return $this->base;
    }

    /**
     * Set the proper constraints on the relationship table.
     */
    protected function constrain(): void
    {
        $this->table->where($this->model->key(), '=', $this->foreignValue());
    }
}
