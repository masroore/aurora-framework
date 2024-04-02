<?php

namespace Aurora\Routing;

use Aurora\Bundle;
use Aurora\Event;
use Aurora\IoC;
use Aurora\Request;
use Aurora\Response;
use Aurora\Str;
use Aurora\View;
use FilesystemIterator as fIterator;

abstract class Controller
{
    /**
     * The event name for the Aurora controller factory.
     *
     * @var string
     */
    public const FACTORY = 'aurora.controller.factory';

    /**
     * @var string
     */
    public const SOURCE_DIRECTORY = 'controllers';

    /**
     * @var string
     */
    public const CLASS_SUFFIX = 'Controller';

    /**
     * The layout being used by the controller.
     *
     * @var string
     */
    public $layout;

    /**
     * The bundle the controller belongs to.
     *
     * @var string
     */
    public $bundle;

    /**
     * Indicates if the controller uses RESTful routing.
     *
     * @var bool
     */
    public $restful = false;

    /**
     * The filters assigned to the controller.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Create a new Controller instance.
     */
    public function __construct()
    {
        // If the controller has specified a layout to be used when rendering
        // views, we will instantiate the layout instance and set it to the
        // layout property, replacing the string layout name.
        if (null !== $this->layout) {
            $this->layout = $this->layout();
        }
    }

    /**
     * Magic Method to handle calls to undefined controller functions.
     */
    public function __call(string $method, array $parameters): Response
    {
        return Response::error('404');
    }

    /**
     * Dynamically resolve items from the application IoC container.
     *
     * <code>
     *        // Retrieve an object registered in the container
     *        $mailer = $this->mailer;
     *
     *        // Equivalent call using the IoC container instance
     *        $mailer = IoC::resolve('mailer');
     * </code>
     */
    public function __get($key)
    {
        if (IoC::registered($key)) {
            return IoC::resolve($key);
        }
    }

    /**
     * Create the layout that is assigned to the controller.
     */
    public function layout(): View
    {
        if (Str::startsWith($this->layout, 'name: ')) {
            return View::of(substr($this->layout, 6));
        }

        return View::make($this->layout);
    }

    /**
     * Detect all of the controllers for a given bundle.
     */
    public static function detect(string $bundle = DEFAULT_BUNDLE, ?string $directory = null): array
    {
        if (null === $directory) {
            $directory = Bundle::path($bundle) . self::SOURCE_DIRECTORY;
        }

        // First we'll get the root path to the directory housing all of
        // the bundle's controllers. This will be used later to figure
        // out the identifiers needed for the found controllers.
        $root = Bundle::path($bundle) . self::SOURCE_DIRECTORY . DS;

        $controllers = [];

        $items = new fIterator($directory, fIterator::SKIP_DOTS);

        foreach ($items as $item) {
            // If the item is a directory, we will recurse back into the function
            // to detect all of the nested controllers and we will keep adding
            // them into the array of controllers for the bundle.
            if ($item->isDir()) {
                $nested = static::detect($bundle, $item->getRealPath());

                $controllers = array_merge($controllers, $nested);
            }

            // If the item is a file, we'll assume it is a controller and we
            // will build the identifier string for the controller that we
            // can pass into the route's controller method.
            else {
                $controller = str_replace([$root, EXT], '', $item->getRealPath());

                $controller = str_replace(DS, '.', $controller);

                $controllers[] = Bundle::identifier($bundle, $controller);
            }
        }

        return $controllers;
    }

    /**
     * Call an action method on a controller.
     *
     * <code>
     *        // Call the "show" method on the "user" controller
     *        $response = Controller::call('user@show');
     *
     *        // Call the "user/admin" controller and pass parameters
     *        $response = Controller::call('user.admin@profile', array($username));
     * </code>
     */
    public static function call(string $destination, array $parameters = []): Response
    {
        static::references($destination, $parameters);

        [$bundle, $destination] = Bundle::parse($destination);

        // We will always start the bundle, just in case the developer is pointing
        // a route to another bundle. This allows us to lazy load the bundle and
        // improve speed since the bundle is not loaded on every request.
        Bundle::start($bundle);

        [$name, $method] = explode('@', $destination);

        $controller = static::resolve($bundle, $name);

        // For convenience we will set the current controller and action on the
        // Request's route instance so they can be easily accessed from the
        // application. This is sometimes useful for dynamic situations.
        if (null !== ($route = Request::route())) {
            $route->controller = $name;

            $route->controller_action = $method;
        }

        // If the controller could not be resolved, we're out of options and
        // will return the 404 error response. If we found the controller,
        // we can execute the requested method on the instance.
        if (null === $controller) {
            return Event::first('404');
        }

        return $controller->execute($method, $parameters);
    }

    /**
     * Resolve a bundle and controller name to a controller instance.
     */
    public static function resolve(string $bundle, string $controller): ?self
    {
        if (!static::load($bundle, $controller)) {
            return null;
        }

        $identifier = Bundle::identifier($bundle, $controller);

        // If the controller is registered in the IoC container, we will resolve
        // it out of the container. Using constructor injection on controllers
        // via the container allows more flexible applications.
        $resolver = 'controller: ' . $identifier;

        if (IoC::registered($resolver)) {
            return IoC::resolve($resolver);
        }

        $controller = static::format($bundle, $controller);

        // If we couldn't resolve the controller out of the IoC container we'll
        // format the controller name into its proper class name and load it
        // by convention out of the bundle's controller directory.
        if (Event::listeners(static::FACTORY)) {
            return Event::first(static::FACTORY, $controller);
        }

        return new $controller();
    }

    /**
     * Execute a controller method with the given parameters.
     */
    public function execute(string $method, array $parameters = []): Response
    {
        $filters = $this->filters('before', $method);

        // Again, as was the case with route closures, if the controller "before"
        // filters return a response, it will be considered the response to the
        // request and the controller method will not be used.
        $response = Filter::run($filters, $parameters, true);

        if (null === $response) {
            $this->before();

            $response = $this->response($method, $parameters);
        }

        $response = Response::prepare($response);

        // The "after" function on the controller is simply a convenient hook
        // so the developer can work on the response before it's returned to
        // the browser. This is useful for templating, etc.
        $this->after($response);

        Filter::run($this->filters('after', $method), [$response]);

        return $response;
    }

    /**
     * This function is called before the action is executed.
     */
    public function before(): void
    {
    }

    /**
     * Execute a controller action and return the response.
     *
     * Unlike the "execute" method, no filters will be run and the response
     * from the controller action will not be changed in any way before it
     * is returned to the consumer.
     */
    public function response(string $method, array $parameters = [])
    {
        // The developer may mark the controller as being "RESTful" which
        // indicates that the controller actions are prefixed with the
        // HTTP verb they respond to rather than the word "action".
        if ($this->restful) {
            $action = strtolower(Request::method()) . '_' . $method;
        } else {
            if (Str::startsWith($method, '_')) {
                // Protect controller methods whose name begins with an underscore
                throw new \Exception('Invalid method');
            }

            $action = $method;
        }

        $response = \call_user_func_array([$this, $action], $parameters);

        // If the controller has specified a layout view the response
        // returned by the controller method will be bound to that
        // view and the layout will be considered the response.
        if (null === $response && null !== $this->layout) {
            $response = $this->layout;
        }

        return $response;
    }

    /**
     * This function is called after the action is executed.
     */
    public function after(Response $response): void
    {
    }

    /**
     * Replace all back-references on the given destination.
     */
    protected static function references(string &$destination, array &$parameters): array
    {
        // Controller delegates may use back-references to the action parameters,
        // which allows the developer to setup more flexible routes to various
        // controllers with much less code than would be usual.
        foreach ($parameters as $key => $value) {
            if (!\is_string($value)) {
                continue;
            }

            $search = '(:' . ($key + 1) . ')';

            $destination = str_replace($search, $value, $destination, $count);

            if ($count > 0) {
                unset($parameters[$key]);
            }
        }

        return [$destination, $parameters];
    }

    /**
     * Load the file for a given controller.
     *
     * @param string $bundle
     * @param string $controller
     *
     * @return bool
     */
    protected static function load($bundle, $controller)
    {
        $controller = mb_strtolower(str_replace('.', DS, $controller));
        $path = Bundle::path($bundle) . self::SOURCE_DIRECTORY . DS . $controller . EXT;

        if (file_exists($path)) {
            require_once $path;

            return true;
        }

        return false;
    }

    /**
     * Format a bundle and controller identifier into the controller's class name.
     */
    protected static function format(string $bundle, string $controller): string
    {
        // Strip nested directory names from the controller class name
        // "one.two.home" becomes "HomeController" instead of "One_Two_HomeController"
        $controller = Str::afterLast($controller, '.');

        return Bundle::classPrefix($bundle) . Str::classify($controller) . self::CLASS_SUFFIX;
    }

    /**
     * Get an array of filter names defined for the destination.
     */
    protected function filters(string $event, string $method): array
    {
        if (!isset($this->filters[$event])) {
            return [];
        }

        $filters = [];

        foreach ($this->filters[$event] as $collection) {
            if ($collection->applies($method)) {
                $filters[] = $collection;
            }
        }

        return $filters;
    }

    /**
     * Register filters on the controller's methods.
     *
     * <code>
     *        // Set a "foo" after filter on the controller
     *        $this->filter('before', 'foo');
     *
     *        // Set several filters on an explicit group of methods
     *        $this->filter('after', 'foo|bar')->only(array('user', 'profile'));
     * </code>
     *
     * @param array|string $filters
     * @param mixed|null   $parameters
     */
    protected function filter(string $event, $filters, $parameters = null): Filters
    {
        $this->filters[$event][] = new Filters($filters, $parameters);

        return $this->filters[$event][\count($this->filters[$event]) - 1];
    }
}
