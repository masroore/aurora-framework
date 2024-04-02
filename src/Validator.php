<?php

namespace Aurora;

class Validator
{
    /**
     * The array being validated.
     *
     * @var array
     */
    public $attributes;

    /**
     * The post-validation error messages.
     *
     * @var Messages
     */
    public $errors;

    /**
     * The registered custom validators.
     *
     * @var array
     */
    protected static $validators = [];

    /**
     * The validation rules.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * The validation messages.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * The database connection that should be used by the validator.
     *
     * @var Database\Connection
     */
    protected $db;

    /**
     * The bundle for which the validation is being run.
     *
     * @var string
     */
    protected $bundle = DEFAULT_BUNDLE;

    /**
     * The language that should be used when retrieving error messages.
     *
     * @var string
     */
    protected $language;

    /**
     * The size related validation rules.
     *
     * @var array
     */
    protected $size_rules = ['size', 'between', 'min', 'max'];

    /**
     * The numeric related validation rules.
     *
     * @var array
     */
    protected $numeric_rules = ['numeric', 'integer'];

    /**
     * Create a new validator instance.
     *
     * @param array $rules
     * @param array $messages
     */
    public function __construct($attributes, $rules, $messages = [])
    {
        foreach ($rules as $key => &$rule) {
            $rule = (\is_string($rule)) ? explode('|', $rule) : $rule;
        }

        $this->rules = $rules;
        $this->messages = $messages;
        $this->attributes = (\is_object($attributes)) ? get_object_vars($attributes) : $attributes;
    }

    /**
     * Dynamically handle calls to custom registered validators.
     */
    public function __call($method, $parameters)
    {
        // First we will slice the "validate_" prefix off of the validator since
        // custom validators aren't registered with such a prefix, then we can
        // just call the method with the given parameters.
        if (isset(static::$validators[$method = mb_substr($method, 9)])) {
            return \call_user_func_array(static::$validators[$method], $parameters);
        }

        throw new \Exception("Method [$method] does not exist.");
    }

    /**
     * Create a new validator instance.
     *
     * @param array $attributes
     * @param array $rules
     * @param array $messages
     *
     * @return Validator
     */
    public static function make($attributes, $rules, $messages = [])
    {
        return new static($attributes, $rules, $messages);
    }

    /**
     * Register a custom validator.
     *
     * @param string   $name
     * @param \Closure $validator
     */
    public static function register($name, $validator): void
    {
        static::$validators[$name] = $validator;
    }

    /**
     * Validate the target array using the specified validation rules.
     *
     * @return bool
     */
    public function passes()
    {
        return $this->valid();
    }

    /**
     * Validate the target array using the specified validation rules.
     *
     * @return bool
     */
    public function valid()
    {
        $this->errors = new Messages();

        foreach ($this->rules as $attribute => $rules) {
            foreach ($rules as $rule) {
                $this->check($attribute, $rule);
            }
        }

        return 0 === \count($this->errors->messages);
    }

    /**
     * Validate the target array using the specified validation rules.
     *
     * @return bool
     */
    public function fails()
    {
        return $this->invalid();
    }

    /**
     * Validate the target array using the specified validation rules.
     *
     * @return bool
     */
    public function invalid()
    {
        return !$this->valid();
    }

    /**
     * Set the bundle that the validator is running for.
     *
     * The bundle determines which bundle the language lines will be loaded from.
     *
     * @param string $bundle
     *
     * @return Validator
     */
    public function bundle($bundle)
    {
        $this->bundle = $bundle;

        return $this;
    }

    /**
     * Set the language that should be used when retrieving error messages.
     *
     * @param string $language
     *
     * @return Validator
     */
    public function speaks($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Set the database connection that should be used by the validator.
     *
     * @return Validator
     */
    public function connection(Database\Connection $connection)
    {
        $this->db = $connection;

        return $this;
    }

    /**
     * Evaluate an attribute against a validation rule.
     *
     * @param string $attribute
     * @param string $rule
     */
    protected function check($attribute, $rule): void
    {
        [$rule, $parameters] = $this->parse($rule);

        $value = array_get($this->attributes, $attribute);

        // Before running the validator, we need to verify that the attribute and rule
        // combination is actually validatable. Only the "accepted" rule implies that
        // the attribute is "required", so if the attribute does not exist, the other
        // rules will not be run for the attribute.
        $validatable = $this->validatable($rule, $attribute, $value);

        if ($validatable && !$this->{'validate_' . $rule}($attribute, $value, $parameters, $this)) {
            $this->error($attribute, $rule, $parameters);
        }
    }

    /**
     * Extract the rule name and parameters from a rule.
     *
     * @param string $rule
     *
     * @return array
     */
    protected function parse($rule)
    {
        $parameters = [];

        // The format for specifying validation rules and parameters follows a
        // {rule}:{parameters} formatting convention. For instance, the rule
        // "max:3" specifies that the value may only be 3 characters long.
        if (false !== ($colon = mb_strpos($rule, ':'))) {
            $parameters = str_getcsv(mb_substr($rule, $colon + 1));
        }

        return [is_numeric($colon) ? mb_substr($rule, 0, $colon) : $rule, $parameters];
    }

    /**
     * Determine if an attribute is validatable.
     *
     * To be considered validatable, the attribute must either exist, or the rule
     * being checked must implicitly validate "required", such as the "required"
     * rule or the "accepted" rule.
     *
     * @param string $rule
     * @param string $attribute
     *
     * @return bool
     */
    protected function validatable($rule, $attribute, $value)
    {
        return $this->validate_required($attribute, $value) || $this->implicit($rule);
    }

    /**
     * Validate that a required attribute exists in the attributes array.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_required($attribute, $value)
    {
        if (null === $value) {
            return false;
        }

        if (\is_string($value) && '' === trim($value)) {
            return false;
        }

        if (null !== Input::file($attribute) && \is_array($value) && '' === $value['tmp_name']) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a given rule implies that the attribute is required.
     *
     * @param string $rule
     *
     * @return bool
     */
    protected function implicit($rule)
    {
        return 'required' === $rule || 'accepted' === $rule || 'required_with' === $rule;
    }

    /**
     * Add an error message to the validator's collection of messages.
     *
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     */
    protected function error($attribute, $rule, $parameters): void
    {
        $message = $this->replace($this->message($attribute, $rule), $attribute, $rule, $parameters);

        $this->errors->add($attribute, $message);
    }

    /**
     * Replace all error message place-holders with actual values.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace($message, $attribute, $rule, $parameters)
    {
        $message = str_replace(':attribute', $this->attribute($attribute), $message);

        if (method_exists($this, $replacer = 'replace_' . $rule)) {
            $message = $this->$replacer($message, $attribute, $rule, $parameters);
        }

        return $message;
    }

    /**
     * Get the displayable name for a given attribute.
     *
     * @param string $attribute
     *
     * @return string
     */
    protected function attribute($attribute)
    {
        $bundle = Bundle::prefix($this->bundle);

        // More reader friendly versions of the attribute names may be stored
        // in the validation language file, allowing a more readable version
        // of the attribute name in the message.
        $line = "{$bundle}validation.attributes.{$attribute}";

        if (Lang::has($line, $this->language)) {
            return Lang::line($line)->get($this->language);
        }

        // If no language line has been specified for the attribute, all of
        // the underscores are removed from the attribute name and that
        // will be used as the attribute name.

        return str_replace('_', ' ', $attribute);
    }

    /**
     * Get the proper error message for an attribute and rule.
     *
     * @param string $attribute
     * @param string $rule
     *
     * @return string
     */
    protected function message($attribute, $rule)
    {
        $bundle = Bundle::prefix($this->bundle);

        // First we'll check for developer specified, attribute specific messages.
        // These messages take first priority. They allow the fine-grained tuning
        // of error messages for each rule.
        $custom = $attribute . '_' . $rule;

        if (\array_key_exists($custom, $this->messages)) {
            return $this->messages[$custom];
        }

        if (Lang::has($custom = "{$bundle}validation.custom.{$custom}", $this->language)) {
            return Lang::line($custom)->get($this->language);
        }

        // Next we'll check for developer specified, rule specific error messages.
        // These allow the developer to override the error message for an entire
        // rule, regardless of the attribute being validated by that rule.
        if (\array_key_exists($rule, $this->messages)) {
            return $this->messages[$rule];
        }

        // If the rule being validated is a "size" rule, we will need to gather
        // the specific size message for the type of attribute being validated,
        // either a number, file, or string.
        if (\in_array($rule, $this->size_rules, true)) {
            return $this->size_message($bundle, $attribute, $rule);
        }

        // If no developer specified messages have been set, and no other special
        // messages apply to the rule, we will just pull the default validation
        // message from the validation language file.
        $line = "{$bundle}validation.{$rule}";

        return Lang::line($line)->get($this->language);
    }

    /**
     * Get the proper error message for an attribute and size rule.
     *
     * @param string $bundle
     * @param string $attribute
     * @param string $rule
     *
     * @return string
     */
    protected function size_message($bundle, $attribute, $rule)
    {
        // There are three different types of size validations. The attribute
        // may be either a number, file, or a string, so we'll check a few
        // things to figure out which one it is.
        if ($this->has_rule($attribute, $this->numeric_rules)) {
            $line = 'numeric';
        }
        // We assume that attributes present in the $_FILES array are files,
        // which makes sense. If the attribute doesn't have numeric rules
        // and isn't a file, it's a string.
        elseif (\array_key_exists($attribute, Input::file())) {
            $line = 'file';
        } else {
            $line = 'string';
        }

        return Lang::line("{$bundle}validation.{$rule}.{$line}")->get($this->language);
    }

    /**
     * Determine if an attribute has a rule assigned to it.
     *
     * @param string $attribute
     * @param array  $rules
     *
     * @return bool
     */
    protected function has_rule($attribute, $rules)
    {
        foreach ($this->rules[$attribute] as $rule) {
            [$rule, $parameters] = $this->parse($rule);

            if (\in_array($rule, $rules, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that an attribute exists in the attributes array, if another
     * attribute exists in the attributes array.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_required_with($attribute, $value, $parameters)
    {
        $other = $parameters[0];
        $other_value = array_get($this->attributes, $other);

        if ($this->validate_required($other, $other_value)) {
            return $this->validate_required($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute has a matching confirmation attribute.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_confirmed($attribute, $value)
    {
        return $this->validate_same($attribute, $value, [$attribute . '_confirmation']);
    }

    /**
     * Validate that an attribute is the same as another attribute.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_same($attribute, $value, $parameters)
    {
        $other = $parameters[0];

        return \array_key_exists($other, $this->attributes) && $value === $this->attributes[$other];
    }

    /**
     * Validate that an attribute was "accepted".
     *
     * This validation rule implies the attribute is "required".
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_accepted($attribute, $value)
    {
        return $this->validate_required($attribute, $value) && ('yes' === $value || '1' === $value || 'on' === $value);
    }

    /**
     * Validate that an attribute is different from another attribute.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_different($attribute, $value, $parameters)
    {
        $other = $parameters[0];

        return \array_key_exists($other, $this->attributes) && $value !== $this->attributes[$other];
    }

    /**
     * Validate that an attribute is numeric.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_numeric($attribute, $value)
    {
        return is_numeric($value);
    }

    /**
     * Validate that an attribute is an integer.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_integer($attribute, $value)
    {
        return false !== filter_var($value, \FILTER_VALIDATE_INT);
    }

    /**
     * Validate the size of an attribute.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_size($attribute, $value, $parameters)
    {
        return $this->size($attribute, $value) === $parameters[0];
    }

    /**
     * Get the size of an attribute.
     *
     * @param string $attribute
     */
    protected function size($attribute, $value)
    {
        // This method will determine if the attribute is a number, string, or file and
        // return the proper size accordingly. If it is a number, the number itself is
        // the size; if it is a file, the kilobytes is the size; if it is a
        // string, the length is the size.
        if (is_numeric($value) && $this->has_rule($attribute, $this->numeric_rules)) {
            return $this->attributes[$attribute];
        }
        if (\array_key_exists($attribute, Input::file())) {
            return $value['size'] / 1024;
        }

        return Str::length(trim($value));
    }

    /**
     * Validate the size of an attribute is between a set of values.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_between($attribute, $value, $parameters)
    {
        $size = $this->size($attribute, $value);

        return $size >= $parameters[0] && $size <= $parameters[1];
    }

    /**
     * Validate the size of an attribute is greater than a minimum value.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_min($attribute, $value, $parameters)
    {
        return $this->size($attribute, $value) >= $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_max($attribute, $value, $parameters)
    {
        return $this->size($attribute, $value) <= $parameters[0];
    }

    /**
     * Validate an attribute is contained within a list of values.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_in($attribute, $value, $parameters)
    {
        return \in_array($value, $parameters, true);
    }

    /**
     * Validate an attribute is not contained within a list of values.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_not_in($attribute, $value, $parameters)
    {
        return !\in_array($value, $parameters, true);
    }

    /**
     * Validate the uniqueness of an attribute value on a given database table.
     *
     * If a database column is not specified, the attribute will be used.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_unique($attribute, $value, $parameters)
    {
        // We allow the table column to be specified just in case the column does
        // not have the same name as the attribute. It must be within the second
        // parameter position, right after the database table name.
        if (isset($parameters[1])) {
            $attribute = $parameters[1];
        }

        $query = $this->db()->table($parameters[0])->where($attribute, '=', $value);

        // We also allow an ID to be specified that will not be included in the
        // uniqueness check. This makes updating columns easier since it is
        // fine for the given ID to exist in the table.
        if (isset($parameters[2])) {
            $id = (isset($parameters[3])) ? $parameters[3] : 'id';

            $query->where($id, '<>', $parameters[2]);
        }

        return 0 === $query->count();
    }

    /**
     * Get the database connection for the Validator.
     *
     * @return Database\Connection
     */
    protected function db()
    {
        if (null !== $this->db) {
            return $this->db;
        }

        return $this->db = Database::connection();
    }

    /**
     * Validate the existence of an attribute value in a database table.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_exists($attribute, $value, $parameters)
    {
        if (isset($parameters[1])) {
            $attribute = $parameters[1];
        }

        // Grab the number of elements we are looking for. If the given value is
        // in array, we'll count all of the values in the array, otherwise we
        // can just make sure the count is greater or equal to one.
        $count = (\is_array($value)) ? \count($value) : 1;

        $query = $this->db()->table($parameters[0]);

        // If the given value is an array, we will check for the existence of
        // all the values in the database, otherwise we'll check for the
        // presence of the single given value in the database.
        if (\is_array($value)) {
            $query = $query->whereIn($attribute, $value);
        } else {
            $query = $query->where($attribute, '=', $value);
        }

        return $query->count() >= $count;
    }

    /**
     * Validate that an attribute is a valid IP.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_ip($attribute, $value)
    {
        return false !== filter_var($value, \FILTER_VALIDATE_IP);
    }

    /**
     * Validate that an attribute is a valid e-mail address.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_email($attribute, $value)
    {
        return false !== filter_var($value, \FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate that an attribute is a valid URL.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_url($attribute, $value)
    {
        return false !== filter_var($value, \FILTER_VALIDATE_URL);
    }

    /**
     * Validate that an attribute is an active URL.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_active_url($attribute, $value)
    {
        $url = str_replace(['http://', 'https://', 'ftp://'], '', Str::lower($value));

        return ('' !== trim($url)) ? checkdnsrr($url) : false;
    }

    /**
     * Validate the MIME type of a file is an image MIME type.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_image($attribute, $value)
    {
        return $this->validate_mimes($attribute, $value, ['jpg', 'png', 'gif', 'bmp']);
    }

    /**
     * Validate the MIME type of a file upload attribute is in a set of MIME types.
     *
     * @param string $attribute
     * @param array  $value
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_mimes($attribute, $value, $parameters)
    {
        if (!\is_array($value) || '' === array_get($value, 'tmp_name', '')) {
            return true;
        }

        foreach ($parameters as $extension) {
            if (File::is($extension, $value['tmp_name'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that an attribute contains only alphabetic characters.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_alpha($attribute, $value)
    {
        return preg_match('/^([a-z])+$/i', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_alpha_num($attribute, $value)
    {
        return preg_match('/^([a-z0-9])+$/i', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters, dashes, and underscores.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_alpha_dash($attribute, $value)
    {
        return preg_match('/^([-a-z0-9_-])+$/i', $value);
    }

    /**
     * Validate that an attribute passes a regular expression check.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_match($attribute, $value, $parameters)
    {
        return preg_match($parameters[0], $value);
    }

    /**
     * Validate that an attribute is an array.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function validate_array($attribute, $value)
    {
        return \is_array($value);
    }

    /**
     * Validate that an attribute of type array has a specific count.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_count($attribute, $value, $parameters)
    {
        return \is_array($value) && \count($value) === $parameters[0];
    }

    /**
     * Validate that an attribute of type array has a minimum of elements.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_countmin($attribute, $value, $parameters)
    {
        return \is_array($value) && \count($value) >= $parameters[0];
    }

    /**
     * Validate that an attribute of type array has a maximum of elements.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_countmax($attribute, $value, $parameters)
    {
        return \is_array($value) && \count($value) <= $parameters[0];
    }

    /**
     * Validate that an attribute of type array has elements between max and min.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_countbetween($attribute, $value, $parameters)
    {
        return \is_array($value) && \count($value) >= $parameters[0] && \count($value) <= $parameters[1];
    }

    /**
     * Validate the date is before a given date.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_before($attribute, $value, $parameters)
    {
        return strtotime($value) < strtotime($parameters[0]);
    }

    /**
     * Validate the date is after a given date.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_after($attribute, $value, $parameters)
    {
        return strtotime($value) > strtotime($parameters[0]);
    }

    /**
     * Validate the date conforms to a given format.
     *
     * @param string $attribute
     * @param array  $parameters
     *
     * @return bool
     */
    protected function validate_date_format($attribute, $value, $parameters)
    {
        return false !== date_create_from_format($parameters[0], $value);
    }

    /**
     * Replace all place-holders for the required_with rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_required_with($message, $attribute, $rule, $parameters)
    {
        return str_replace(':field', $this->attribute($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the between rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_between($message, $attribute, $rule, $parameters)
    {
        return str_replace([':min', ':max'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the size rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_size($message, $attribute, $rule, $parameters)
    {
        return str_replace(':size', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the min rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_min($message, $attribute, $rule, $parameters)
    {
        return str_replace(':min', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the max rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_max($message, $attribute, $rule, $parameters)
    {
        return str_replace(':max', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the in rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_in($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the not_in rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_not_in($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the mimes rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_mimes($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the same rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_same($message, $attribute, $rule, $parameters)
    {
        return str_replace(':other', $this->attribute($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the different rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_different($message, $attribute, $rule, $parameters)
    {
        return str_replace(':other', $this->attribute($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the before rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_before($message, $attribute, $rule, $parameters)
    {
        return str_replace(':date', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the after rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_after($message, $attribute, $rule, $parameters)
    {
        return str_replace(':date', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the count rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_count($message, $attribute, $rule, $parameters)
    {
        return str_replace(':count', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the countmin rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_countmin($message, $attribute, $rule, $parameters)
    {
        return str_replace(':min', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the countmax rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_countmax($message, $attribute, $rule, $parameters)
    {
        return str_replace(':max', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the between rule.
     *
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return string
     */
    protected function replace_countbetween($message, $attribute, $rule, $parameters)
    {
        return str_replace([':min', ':max'], $parameters, $message);
    }
}
