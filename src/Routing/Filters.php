<?php

namespace Aurora\Routing;

use Aurora\Bundle;
use Aurora\Request;

class Filters
{
    /**
     * The filters contained by the collection.
     *
     * @var array|string
     */
    public $filters = [];

    /**
     * The parameters specified for the filter.
     */
    public $parameters;

    /**
     * The included controller methods.
     *
     * @var array
     */
    public $only = [];

    /**
     * The excluded controller methods.
     *
     * @var array
     */
    public $except = [];

    /**
     * The HTTP methods for which the filter applies.
     *
     * @var array
     */
    public $methods = [];

    /**
     * Create a new filter collection instance.
     *
     * @param array|string $filters
     * @param mixed|null   $parameters
     */
    public function __construct($filters, $parameters = null)
    {
        $this->parameters = $parameters;
        $this->filters = Filter::parse($filters);
    }

    /**
     * Parse the filter string, returning the filter name and parameters.
     */
    public function get(string $filter): array
    {
        // If the parameters were specified by passing an array into the collection,
        // then we will simply return those parameters. Combining passed parameters
        // with parameters specified directly in the filter attachment is not
        // currently supported by the framework.
        if (null !== $this->parameters) {
            return [$filter, $this->parameters()];
        }

        // If no parameters were specified when the collection was created, we will
        // check the filter string itself to see if the parameters were injected
        // into the string as raw values, such as "role:admin".
        if (false !== ($colon = mb_strpos(Bundle::element($filter), ':'))) {
            $parameters = explode(',', mb_substr(Bundle::element($filter), $colon + 1));

            // If the filter belongs to a bundle, we need to re-calculate the position
            // of the parameter colon, since we originally calculated it without the
            // bundle identifier because the identifier uses colons as well.
            if (DEFAULT_BUNDLE !== ($bundle = Bundle::name($filter))) {
                $colon = mb_strlen($bundle . '::') + $colon;
            }

            return [mb_substr($filter, 0, $colon), $parameters];
        }

        // If no parameters were specified when the collection was created or
        // in the filter string, we will just return the filter name as is
        // and give back an empty array of parameters.
        return [$filter, []];
    }

    /**
     * Determine if this collection's filters apply to a given method.
     */
    public function applies(string $method): bool
    {
        if (\count($this->only) > 0 && !\in_array($method, $this->only, true)) {
            return false;
        }

        if (\count($this->except) > 0 && \in_array($method, $this->except, true)) {
            return false;
        }

        $request = strtolower(Request::method());

        if (\count($this->methods) > 0 && !\in_array($request, $this->methods, true)) {
            return false;
        }

        return true;
    }

    /**
     * Set the excluded controller methods.
     *
     * <code>
     *        // Specify a filter for all methods except "index"
     *        $this->filter('before', 'auth')->except('index');
     *
     *        // Specify a filter for all methods except "index" and "home"
     *        $this->filter('before', 'auth')->except(array('index', 'home'));
     * </code>
     */
    public function except(array $methods): self
    {
        $this->except = (array)$methods;

        return $this;
    }

    /**
     * Set the included controller methods.
     *
     * <code>
     *        // Specify a filter for only the "index" method
     *        $this->filter('before', 'auth')->only('index');
     *
     *        // Specify a filter for only the "index" and "home" methods
     *        $this->filter('before', 'auth')->only(array('index', 'home'));
     * </code>
     */
    public function only(array $methods): self
    {
        $this->only = (array)$methods;

        return $this;
    }

    /**
     * Set the HTTP methods for which the filter applies.
     *
     * <code>
     *        // Specify that a filter only applies on POST requests
     *        $this->filter('before', 'csrf')->on('post');
     *
     *        // Specify that a filter applies for multiple HTTP request methods
     *        $this->filter('before', 'csrf')->on(array('post', 'put'));
     * </code>
     */
    public function on(array $methods): self
    {
        $this->methods = array_map('strtolower', (array)$methods);

        return $this;
    }

    /**
     * Evaluate the collection's parameters and return a parameters array.
     */
    protected function parameters(): array
    {
        if ($this->parameters instanceof \Closure) {
            $this->parameters = \call_user_func($this->parameters);
        }

        return $this->parameters;
    }
}
