<?php

namespace Aurora\Database\Eloquent;

use Aurora\Messages;
use Aurora\Validator;

/**
 * Aware Models
 *    Self-validating Eloquent Models.
 */
abstract class Aware extends Model
{
    /**
     * Aware Validation Rules.
     *
     * @var array
     */
    public static $rules = [];

    /**
     * Aware Validation Messages.
     *
     * @var array
     */
    public static $messages = [];

    /**
     * Aware Errors.
     *
     * @var Messages
     */
    public $errors;

    /**
     * Create new Aware instance.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [], $exists = false)
    {
        // initialize empty messages object
        $this->errors = new Messages();
        parent::__construct($attributes, $exists);
    }

    /**
     * Magic Method for setting Aware attributes.
     *    ignores unchanged attributes delegates to Eloquent.
     *
     * @param string              $key
     * @param bool|etc|num|string $value
     */
    public function __set($key, $value): void
    {
        // only update an attribute if there's a change
        if (!\array_key_exists($key, $this->attributes) || $this->$key !== $value) {
            parent::__set($key, $value);
        }
    }

    /**
     * Validate the Model
     *    runs the validator and binds any errors to the model.
     *
     * @param array $rules
     * @param array $messages
     *
     * @return bool
     */
    public function valid($rules = [], $messages = [])
    {
        // innocent until proven guilty
        $valid = true;

        if (!empty($rules) || !empty(static::$rules)) {
            // check for overrides
            $rules = (empty($rules)) ? static::$rules : $rules;
            $messages = (empty($messages)) ? static::$messages : $messages;

            // if the model exists, this is an update
            if ($this->exists) {
                // and only include dirty fields
                $data = $this->getDirty();

                // so just validate the fields that are being updated
                $rules = array_intersect_key($rules, $data);
            } else {
                // otherwise validate everything!
                $data = $this->attributes;
            }

            // construct the validator
            $validator = Validator::make($data, $rules, $messages);
            $valid = $validator->valid();

            // if the model is valid, unset old errors
            if ($valid) {
                $this->errors->messages = [];
            } else { // otherwise set the new ones
                $this->errors = $validator->errors;
            }
        }

        return $valid;
    }

    /**
     * onSave
     *  called every time a model is saved - to halt the save, return false.
     *
     * @return bool
     */
    public function onSave()
    {
        return true;
    }

    /**
     * onForceSave
     *  called evertime a model is force_saved - to halt the force_save, return false.
     *
     * @return bool
     */
    public function onForceSave()
    {
        return true;
    }

    /**
     * Save.
     *
     * @param array $rules    :array
     * @param array $messages
     *
     * @return Aware|bool
     */
    public function save($rules = [], $messages = [], ?\Closure $onSave = null)
    {
        // validate
        $valid = $this->valid($rules, $messages);

        // evaluate onSave
        $before = null === $onSave ? $this->onSave() : $onSave($this);

        // check before & valid, then pass to parent
        return ($before && $valid) ? parent::save() : false;
    }

    /**
     * Force Save
     *    attempts to save model even if it doesn't validate.
     *
     * @param array     $rules       :array
     * @param array     $messages    :array
     * @param ?\Closure $onForceSave
     *
     * @return Aware|bool
     */
    public function force_save($rules = [], $messages = [], ?\Closure $onForceSave = null)
    {
        // validate the model
        $this->valid($rules, $messages);

        // execute onForceSave
        $before = null === $onForceSave ? $this->onForceSave() : $onForceSave($this);

        // save regardless of the result of validation
        return $before ? parent::save() : false;
    }
}
