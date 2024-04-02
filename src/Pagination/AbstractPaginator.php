<?php

namespace Aurora\Pagination;

use Aurora\Arr;
use Aurora\Collection;
use Aurora\Str;
use Aurora\Traits\ForwardsCalls;

abstract class AbstractPaginator
{
    use ForwardsCalls;

    /**
     * The default pagination view.
     */
    public static string $defaultView = 'vendor.pagination.bootstrap-4';
    /**
     * The default "simple" pagination view.
     */
    public static string $defaultSimpleView = 'vendor.pagination.simple-bootstrap-4';
    /**
     * The current path resolver callback.
     */
    protected static \Closure $currentPathResolver;
    /**
     * The current page resolver callback.
     */
    protected static \Closure $currentPageResolver;
    /**
     * The number of links to display on each side of current page link.
     */
    public int $onEachSide = 3;
    /**
     * All of the items being paginated.
     */
    protected Collection $items;
    /**
     * The number of items to be shown per page.
     */
    protected int $perPage;
    /**
     * The current page being "viewed".
     */
    protected int $currentPage;
    /**
     * The base path to assign to all URLs.
     */
    protected string $path = '/';
    /**
     * The query parameters to add to all URLs.
     */
    protected array $query = [];
    /**
     * The URL fragment to add to all URLs.
     */
    protected ?string $fragment;
    /**
     * The query string variable used to store the page.
     */
    protected string $pageName = 'page';
    /**
     * The paginator options.
     */
    protected array $options;

    /**
     * Resolve the current request path or return the default value.
     */
    public static function resolveCurrentPath(string $default = '/'): string
    {
        if (isset(static::$currentPathResolver)) {
            return \call_user_func(static::$currentPathResolver);
        }

        return $default;
    }

    /**
     * Set the current request path resolver callback.
     */
    public static function currentPathResolver(\Closure $resolver): void
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * Resolve the current page or return the default value.
     */
    public static function resolveCurrentPage(string $pageName = 'page', int $default = 1): int
    {
        if (isset(static::$currentPageResolver)) {
            return \call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    /**
     * Set the current page resolver callback.
     */
    public static function currentPageResolver(\Closure $resolver): void
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * Indicate that Bootstrap 3 styling should be used for generated links.
     */
    public static function useBootstrapThree(): void
    {
        static::defaultView('pagination::default');
        static::defaultSimpleView('pagination::simple-default');
    }

    /**
     * Set the default pagination view.
     */
    public static function defaultView(string $view): void
    {
        static::$defaultView = $view;
    }

    /**
     * Set the default "simple" pagination view.
     */
    public static function defaultSimpleView(string $view): void
    {
        static::$defaultSimpleView = $view;
    }

    /**
     * Make dynamic calls into the collection.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->getCollection(), $method, $parameters);
    }

    /**
     * Get the paginator's underlying collection.
     */
    public function getCollection(): Collection
    {
        return $this->items;
    }

    /**
     * Render the contents of the paginator when casting to string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->render();
    }

    /**
     * Get the URL for the previous page.
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }

        return null;
    }

    /**
     * Get the current page.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the URL for a given page number.
     */
    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        // If we have any extra query string key / value pairs that need to be added
        // onto the URL, we will put them in query string form and then attach it
        // to the URL. This allows for extra information like sortings storage.
        $parameters = [$this->pageName => $page];

        if (\count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path
            . (Str::contains($this->path, '?') ? '&' : '?')
            . Arr::query($parameters)
            . $this->buildFragment();
    }

    /**
     * Build the full fragment portion of a URL.
     */
    protected function buildFragment(): string
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    /**
     * Create a range of pagination URLs.
     */
    public function getUrlRange(int $start, int $end): array
    {
        return collect(range($start, $end))->mapWithKeys(function ($page) {
            return [$page => $this->url($page)];
        })->all();
    }

    /**
     * Get / set the URL fragment to be appended to URLs.
     */
    public function fragment(?string $fragment = null): string|static|null
    {
        if (null === $fragment) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    /**
     * Add a set of query string values to the paginator.
     */
    public function appends(array|string|null $key, ?string $value = null): static
    {
        if (null === $key) {
            return $this;
        }

        if (\is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * Add an array of query string values.
     */
    protected function appendArray(array $keys): static
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * Add a query string value to the paginator.
     */
    protected function addQuery(string $key, string $value): static
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * Load a set of relationships onto the mixed relationship collection.
     */
    public function loadMorph(string $relation, array $relations): static
    {
        $this->getCollection()->loadMorph($relation, $relations);

        return $this;
    }

    /**
     * Get the slice of items being paginated.
     */
    public function items(): array
    {
        return $this->items->all();
    }

    /**
     * Get the number of the last item in the slice.
     */
    public function lastItem(): ?int
    {
        return \count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
    }

    /**
     * Get the number of the first item in the slice.
     */
    public function firstItem(): ?int
    {
        return \count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * Get the number of items for the current page.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Get the number of items shown per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     */
    public function hasPages(): bool
    {
        return 1 !== $this->currentPage() || $this->hasMorePages();
    }

    /**
     * Determine if the paginator is on the first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage() <= 1;
    }

    /**
     * Get the query string variable used to store the page.
     */
    public function getPageName(): string
    {
        return $this->pageName;
    }

    /**
     * Set the query string variable used to store the page.
     *
     * @return $this
     */
    public function setPageName(string $name): static
    {
        $this->pageName = $name;

        return $this;
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @return $this
     */
    public function withPath(string $path): static
    {
        return $this->setPath($path);
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @return $this
     */
    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the number of links to display on each side of current page link.
     *
     * @return $this
     */
    public function onEachSide(int $count): static
    {
        $this->onEachSide = $count;

        return $this;
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->items->getIterator();
    }

    /**
     * Determine if the list of items is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Determine if the list of items is not empty.
     */
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * Set the paginator's underlying collection.
     *
     * @return $this
     */
    public function setCollection(Collection $collection): static
    {
        $this->items = $collection;

        return $this;
    }

    /**
     * Get the paginator options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Determine if the given item exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->items->has($offset);
    }

    /**
     * Get the item at the given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items->get($offset);
    }

    /**
     * Set the item at the given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items->put($offset, $value);
    }

    /**
     * Unset the item at the given key.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->items->forget($offset);
    }

    /**
     * Determine if the given value is a valid page number.
     */
    protected function isValidPageNumber(int $page): bool
    {
        return $page >= 1 && false !== filter_var($page, \FILTER_VALIDATE_INT);
    }
}
