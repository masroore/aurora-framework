<?php

namespace Aurora\Database\Eloquent\Relationships;

use Aurora\Database\Eloquent\Model;

class HasOne extends HasOneOrMany
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
     * Match eagerly loaded child models to their parent models.
     *
     * @param array $parents
     * @param array $children
     */
    public function match($relationship, &$parents, $children): void
    {
        $foreign = $this->foreign_key();

        $dictionary = [];

        foreach ($children as $child) {
            $dictionary[$child->$foreign] = $child;
        }

        foreach ($parents as $parent) {
            if (\array_key_exists($key = $parent->get_key(), $dictionary)) {
                $parent->relationships[$relationship] = $dictionary[$key];
            }
        }
    }
}
