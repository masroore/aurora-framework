<?php

namespace Aurora\Routing;

use Aurora\Bundle;
use Aurora\Response;
use Aurora\Str;
use Closure;

class Route
{
    /**
     * The URI the route responds to.
     *
     * @var string
     */
    public $uri;

    /**
     * The request method the route responds to.
     *
     * @var string
     */
    public $method;

    /**
     * The bundle in which the route was registered.
     *
     * @var string
     */
    public $bundle;

    /**
     * The name of the controller used by the route.
     *
     * @var string
     */
    public $controller;

    /**
     * The name of the controller action used by the route.
     *
     * @var string
     */
    public $controller_action;

    /**
     * The action that is assigned to the route.
     */
    public $action;

    /**
     * The parameters that will be passed to the route callback.
     *
     * @var array
     */
    public $parameters;

    /**
     * Create a new Route instance.
     *
     * @param string $method
     * @param string $uri
     * @param array  $action
     * @param array  $parameters
     */
    public function __construct($method, $uri, $action, $parameters = [])
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->action = $action;

        // Determine the bundle in which the route was registered. We will know
        // the bundle by using the bundle::handles method, which will return
        // the bundle assigned to that URI.
        $this->bundle = Bundle::handles($uri);

        // We'll set the parameters based on the number of parameters passed
        // compared to the parameters that were needed. If more parameters
        // are needed, we'll merge in the defaults.
        $this->parameters($action, $parameters);
    }

    /**
     * Register a controller with the router.
     *
     * @param array|string $controllers
     * @param array|string $defaults
     */
    public static function controller($controllers, $defaults = 'index'): void
    {
        Router::controller($controllers, $defaults);
    }

    /**
     * Register a secure controller with the router.
     *
     * @param array|string $controllers
     * @param array|string $defaults
     */
    public static function secure_controller($controllers, $defaults = 'index'): void
    {
        Router::controller($controllers, $defaults, true);
    }

    /**
     * Register a GET route with the router.
     *
     * @param array|string $route
     */
    public static function get($route, $action): void
    {
        Router::register('GET', $route, $action);
    }

    /**
     * Register a POST route with the router.
     *
     * @param array|string $route
     */
    public static function post($route, $action): void
    {
        Router::register('POST', $route, $action);
    }

    /**
     * Register a PUT route with the router.
     *
     * @param array|string $route
     */
    public static function put($route, $action): void
    {
        Router::register('PUT', $route, $action);
    }

    /**
     * Register a PATCH route with the router.
     *
     * @param array|string $route
     */
    public static function patch($route, $action): void
    {
        Router::register('PATCH', $route, $action);
    }

    /**
     * Register a DELETE route with the router.
     *
     * @param array|string $route
     */
    public static function delete($route, $action): void
    {
        Router::register('DELETE', $route, $action);
    }

    /**
     * Register a route that handles any request method.
     *
     * @param array|string $route
     */
    public static function any($route, $action): void
    {
        Router::register('*', $route, $action);
    }

    /**
     * Register a group of routes that share attributes.
     */
    public static function group(array $attributes, \Closure $callback): void
    {
        Router::group($attributes, $callback);
    }

    /**
     * Register many request URIs to a single action.
     *
     * @param array $routes
     */
    public static function share($routes, $action): void
    {
        Router::share($routes, $action);
    }

    /**
     * Register a HTTPS route with the router.
     *
     * @param string       $method
     * @param array|string $route
     */
    public static function secure($method, $route, $action): void
    {
        Router::secure($method, $route, $action);
    }

    /**
     * Register a route filter.
     *
     * @param string $name
     */
    public static function filter($name, $callback): void
    {
        Filter::register($name, $callback);
    }

    /**
     * Calls the specified route and returns its response.
     *
     * @param string $method
     * @param string $uri
     *
     * @return Response
     */
    public static function forward($method, $uri)
    {
        return Router::route(mb_strtoupper($method), $uri)->call();
    }

    /**
     * Call a given route and return the route's response.
     *
     * @return Response
     */
    public function call()
    {
        // The route is responsible for running the global filters, and any
        // filters defined on the route itself, since all incoming requests
        // come through a route (either defined or ad-hoc).
        $response = Filter::run($this->filters('before'), [], true);

        if (null === $response) {
            $response = $this->response();
        }

        // We always return a Response instance from the route calls, so
        // we'll use the prepare method on the Response class to make
        // sure we have a valid Response instance.
        $response = Response::prepare($response);

        Filter::run($this->filters('after'), [&$response]);

        return $response;
    }

    /**
     * Execute the route action and return the response.
     *
     * Unlike the "call" method, none of the attached filters will be run.
     */
    public function response()
    {
        // If the action is a string, it is pointing the route to a controller
        // action, and we can just call the action and return its response.
        // We'll just pass the action off to the Controller class.
        $delegate = $this->delegate();

        if (null !== $delegate) {
            return Controller::call($delegate, $this->parameters);
        }

        // If the route does not have a delegate, then it must be a Closure
        // instance or have a Closure in its action array, so we will try
        // to locate the Closure and call it directly.
        $handler = $this->handler();

        if (null !== $handler) {
            return \call_user_func_array($handler, $this->parameters);
        }
    }

    /**
     * Determine if the route has a given name.
     *
     * <code>
     *        // Determine if the route is the "login" route
     *        $login = Request::route()->is('login');
     * </code>
     *
     * @param string $name
     *
     * @return bool
     */
    public function is($name)
    {
        return array_get($this->action, 'as') === $name;
    }

    /**
     * Set the parameters array to the correct value.
     *
     * @param array $action
     * @param array $parameters
     */
    protected function parameters($action, $parameters): void
    {
        $defaults = (array)array_get($action, 'defaults');

        // If there are less parameters than wildcards, we will figure out how
        // many parameters we need to inject from the array of defaults and
        // merge them into the main array for the route.
        if (\count($defaults) > \count($parameters)) {
            $defaults = \array_slice($defaults, \count($parameters));

            $parameters = array_merge($parameters, $defaults);
        }

        $this->parameters = $parameters;
    }

    /**
     * Get the filters that are attached to the route for a given event.
     *
     * @param string $event
     *
     * @return array
     */
    protected function filters($event)
    {
        $global = Bundle::prefix($this->bundle) . $event;

        $filters = array_unique([$event, $global]);

        // Next we will check to see if there are any filters attached to
        // the route for the given event. If there are, we'll merge them
        // in with the global filters for the event.
        if (isset($this->action[$event])) {
            $assigned = Filter::parse($this->action[$event]);

            $filters = array_merge($filters, $assigned);
        }

        // Next we will attach any pattern type filters to the array of
        // filters as these are matched to the route by the route's
        // URI and not explicitly attached to routes.
        if ('before' === $event) {
            $filters = array_merge($filters, $this->patterns());
        }

        return [new Filters($filters)];
    }

    /**
     * Get the pattern filters for the route.
     *
     * @return array
     */
    protected function patterns()
    {
        $filters = [];

        // We will simply iterate through the registered patterns and
        // check the URI pattern against the URI for the route and
        // if they match we'll attach the filter.
        foreach (Filter::$patterns as $pattern => $filter) {
            if (Str::is($pattern, $this->uri)) {
                // If the filter provided is an array then we need to register
                // the filter before we can assign it to the route.
                if (\is_array($filter)) {
                    [$filter, $callback] = array_values($filter);

                    Filter::register($filter, $callback);
                }

                $filters[] = $filter;
            }
        }

        return (array)$filters;
    }

    /**
     * Get the controller action delegate assigned to the route.
     *
     * If no delegate is assigned, null will be returned by the method.
     *
     * @return string
     */
    protected function delegate()
    {
        return array_get($this->action, 'uses');
    }

    /**
     * Get the anonymous function assigned to handle the route.
     *
     * @return \Closure
     */
    protected function handler()
    {
        return array_first($this->action, static fn ($key, $value) => $value instanceof \Closure);
    }
}
