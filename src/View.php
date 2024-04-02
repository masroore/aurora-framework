<?php

namespace Aurora;

class View implements \ArrayAccess
{
    /**
     * The Aurora view loader event name.
     *
     * @var string
     */
    final public const loader = 'aurora.view.loader';
    /**
     * The Aurora view engine event name.
     *
     * @var string
     */
    final public const engine = 'aurora.view.engine';

    /**
     * All of the shared view data.
     */
    public static array $shared = [];

    /**
     * All of the registered view names.
     */
    public static array $names = [];

    /**
     * The cache content of loaded view files.
     */
    public static array $cache = [];

    /**
     * THe last view to be rendered.
     */
    public static array $last = [];

    /**
     * The render operations taking place.
     */
    public static int $renderCount = 0;

    /**
     * The name of the view.
     */
    public string $view;

    /**
     * The view data.
     */
    public array $data;

    /**
     * The path to the view on disk.
     */
    public string $path;

    /**
     * Create a new view instance.
     *
     * <code>
     *        // Create a new view instance
     *        $view = new View('home.index');
     *
     *        // Create a new view instance of a bundle's view
     *        $view = new View('admin::home.index');
     *
     *        // Create a new view instance with bound data
     *        $view = new View('home.index', array('name' => 'Taylor'));
     * </code>
     */
    public function __construct(string $view, array $data = [])
    {
        $this->view = $view;
        $this->data = $data;

        // In order to allow developers to load views outside of the normal loading
        // conventions, we'll allow for a raw path to be given in place of the
        // typical view name, giving total freedom on view loading.
        if (Str::startsWith($view, 'path: ')) {
            $this->path = mb_substr($view, 6);
        } else {
            $this->path = $this->path($view);
        }

        // If a session driver has been specified, we will bind an instance of the
        // validation error message container to every view. If an error instance
        // exists in the session, we will use that instance.
        if (!isset($this->data['errors'])) {
            if (Session::started() && Session::has('errors')) {
                $this->data['errors'] = Session::get('errors');
            } else {
                $this->data['errors'] = new Messages();
            }
        }
    }

    /**
     * Get the path to a given view on disk.
     *
     * @return string
     */
    protected function path(string $view): bool|string
    {
        if ($path = self::exists($view, true)) {
            return $path;
        }

        throw new \Exception("View [$view] doesn't exist.");
    }

    /**
     * Determine if the given view exists.
     */
    public static function exists(string $view, bool $return_path = false): bool|string
    {
        if (Str::startsWith($view, 'name: ') && \array_key_exists($name = mb_substr($view, 6), static::$names)) {
            $view = static::$names[$name];
        }

        [$bundle, $view] = Bundle::parse($view);

        $view = str_replace('.', '/', $view);

        // We delegate the determination of view paths to the view loader event
        // so that the developer is free to override and manage the loading
        // of views in any way they see fit for their application.
        $path = Event::until(static::loader, [$bundle, $view]);

        if (null !== $path) {
            return $return_path ? $path : true;
        }

        return false;
    }

    /**
     * Get the evaluated contents of the view.
     */
    public function get(): string
    {
        $__data = $this->data();

        // The contents of each view file is cached in an array for the
        // request since partial views may be rendered inside of for
        // loops which could incur performance penalties.
        $__contents = $this->load();

        ob_start() && extract($__data, \EXTR_SKIP);

        // We'll include the view contents for parsing within a catcher
        // so we can avoid any WSOD errors. If an exception occurs we
        // will throw it out to the exception handler.
        try {
            eval('?>' . $__contents);
        }

        // If we caught an exception, we'll silently flush the output
        // buffer so that no partially rendered views get thrown out
        // to the client and confuse the user with junk.
        catch (\Exception $e) {
            ob_get_clean();

            throw $e;
        }

        $content = ob_get_clean();

        // The view filter event gives us a last chance to modify the
        // evaluated contents of the view and return them. This lets
        // us do something like run the contents through Jade, etc.
        if (Event::listeners('view.filter')) {
            return Event::first('view.filter', [$content, $this->path]);
        }

        return $content;
    }

    /**
     * Get the array of view data for the view instance.
     *
     * The shared view data will be combined with the view data.
     */
    public function data(): array
    {
        $data = array_merge($this->data, static::$shared);

        // All nested views and responses are evaluated before the main view.
        // This allows the assets used by nested views to be added to the
        // asset container before the main view is evaluated.
        foreach ($data as $key => $value) {
            if ($value instanceof self || $value instanceof Response) {
                $data[$key] = $value->render();
            }
        }

        return $data;
    }

    /**
     * Get the evaluated string content of the view.
     */
    public function render(): string
    {
        // We will keep track of the amount of views being rendered so we can flush
        // the section after the complete rendering operation is done. This will
        // clear out the sections for any separate views that may be rendered.
        $this->incrementRender();

        Event::fire("aurora.composing: {$this->view}", [$this]);

        /** @var ?string $contents */
        $contents = null;

        // If there are listeners to the view engine event, we'll pass them
        // the view so they can render it according to their needs, which
        // allows easy attachment of other view parsers.
        if (Event::listeners(static::engine)) {
            $result = Event::until(static::engine, [$this]);

            if (null !== $result) {
                $contents = $result;
            }
        }

        if (null === $contents) {
            $contents = $this->get();
        }

        // Once we've finished rendering the view, we'll decrement the render count
        // so that each sections get flushed out next time a view is created and
        // no old sections are staying around in the memory of an environment.
        $this->decrementRender();
        $this->flushStateIfDoneRendering();

        return $contents;
    }

    /**
     * Increment the rendering counter.
     */
    public function incrementRender(): void
    {
        ++static::$renderCount;
    }

    /**
     * Decrement the rendering counter.
     */
    public function decrementRender(): void
    {
        --static::$renderCount;
    }

    /**
     * Flush all of the section contents if done rendering.
     */
    public function flushStateIfDoneRendering(): void
    {
        if ($this->doneRendering()) {
            $this->flushState();
        }
    }

    /**
     * Check if there are no active render operations.
     */
    public function doneRendering(): bool
    {
        return 0 === static::$renderCount;
    }

    /**
     * Flush all of the factory state like sections and stacks.
     */
    public function flushState(): void
    {
        static::$renderCount = 0;

        $this->flushSections();
    }

    /**
     * Flush all of the sections.
     */
    public function flushSections(): void
    {
        Section::$sections = [];
    }

    /**
     * Get the contents of the view file from disk.
     */
    protected function load(): string
    {
        static::$last = ['name' => $this->view, 'path' => $this->path];

        if (isset(static::$cache[$this->path])) {
            return static::$cache[$this->path];
        }

        return static::$cache[$this->path] = file_get_contents($this->path);
    }

    /**
     * Get the path to a view using the default folder convention.
     */
    public static function file(string $bundle, string $view, string $directory): ?string
    {
        $directory = Str::finish($directory, DS);

        // Views may have either the default PHP file extension or the "Blade"
        // extension, so we will need to check for both in the view path
        // and return the first one we find for the given view.
        if (file_exists($path = $directory . $view . EXT)) {
            return $path;
        }

        if (file_exists($path = $directory . $view . BLADE_EXT)) {
            return $path;
        }

        return null;
    }

    /**
     * Create a new view instance of a named view.
     *
     * <code>
     *        // Create a new named view instance
     *        $view = View::of('profile');
     *
     *        // Create a new named view instance with bound data
     *        $view = View::of('profile', array('name' => 'Taylor'));
     * </code>
     */
    public static function of(string $name, array $data = []): self
    {
        return new static(static::$names[$name], $data);
    }

    /**
     * Assign a name to a view.
     *
     * <code>
     *        // Assign a name to a view
     *        View::name('partials.profile', 'profile');
     *
     *        // Resolve an instance of a named view
     *        $view = View::of('profile');
     * </code>
     */
    public static function name(string $view, string $name): void
    {
        static::$names[$name] = $view;
    }

    /**
     * Register a view composer with the Event class.
     *
     * <code>
     *        // Register a composer for the "home.index" view
     *        View::composer('home.index', function($view)
     *        {
     *            $view['title'] = 'Home';
     *        });
     * </code>
     */
    public static function composer(array|string $views, \Closure $composer): void
    {
        $views = (array)$views;

        foreach ($views as $view) {
            Event::listen("aurora.composing: {$view}", $composer);
        }
    }

    /**
     * Get the rendered contents of a partial from a loop.
     */
    public static function render_each(string $view, array $data, string $iterator, string $empty = 'raw|'): string
    {
        $result = '';

        // If is actually data in the array, we will loop through the data and
        // append an instance of the partial view to the final result HTML,
        // passing in the iterated value of the data array.
        if (\count($data) > 0) {
            foreach ($data as $key => $value) {
                $with = ['key' => $key, $iterator => $value];

                $result .= render($view, $with);
            }
        }

        // If there is no data in the array, we will render the contents of
        // the "empty" view. Alternatively, the "empty view" can be a raw
        // string that is prefixed with "raw|" for convenience.
        else {
            if (Str::startsWith($empty, 'raw|')) {
                $result = mb_substr($empty, 4);
            } else {
                $result = render($empty);
            }
        }

        return $result;
    }

    /**
     * Check if a piece of data is bound to the view.
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a piece of bound data from the view.
     */
    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Get the evaluated string content of the view.
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Magic Method for handling dynamic functions.
     *
     * This method handles calls to dynamic with helpers.
     */
    public function __call(string $method, array $parameters): self
    {
        if (!Str::startsWith($method, 'with')) {
            throw new \Exception(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        return $this->with(Str::camel(mb_substr($method, 4)), $parameters[0]);
    }

    /**
     * Add a key / value pair to the view data.
     *
     * Bound data will be available to the view as variables.
     */
    public function with(array|string $key, mixed $value = null): self
    {
        if (\is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Add a view instance to the view data.
     *
     * <code>
     *        // Add a view instance to a view's data
     *        $view = View::make('foo')->nest('footer', 'partials.footer');
     *
     *        // Equivalent functionality using the "with" method
     *        $view = View::make('foo')->with('footer', View::make('partials.footer'));
     * </code>
     */
    public function nest(string $key, string $view, array $data = []): self
    {
        return $this->with($key, static::make($view, $data));
    }

    /**
     * Create a new view instance.
     *
     * <code>
     *        // Create a new view instance
     *        $view = View::make('home.index');
     *
     *        // Create a new view instance of a bundle's view
     *        $view = View::make('admin::home.index');
     *
     *        // Create a new view instance with bound data
     *        $view = View::make('home.index', array('name' => 'Taylor'));
     * </code>
     */
    public static function make(string $view, array $data = []): self
    {
        return new static($view, $data);
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * Shared view data is accessible to every view created by the application.
     */
    public function shares(string $key, mixed $value): self
    {
        static::share($key, $value);

        return $this;
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * Shared view data is accessible to every view created by the application.
     */
    public static function share(string $key, mixed $value): void
    {
        static::$shared[$key] = $value;
    }

    /**
     * Determine if a piece of data is bound.
     */
    public function offsetExists(mixed $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * Get a piece of bound data to the view.
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->data[$key];
    }

    /**
     * Set a piece of data on the view.
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->with($key, $value);
    }

    /**
     * Unset a piece of data from the view.
     */
    public function offsetUnset(mixed $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Get a piece of data from the view.
     */
    public function &__get(mixed $key): mixed
    {
        return $this->data[$key];
    }

    /**
     * Set a piece of data on the view.
     */
    public function __set(mixed $key, mixed $value): void
    {
        $this->with($key, $value);
    }
}
