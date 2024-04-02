<?php

namespace Aurora\Pagination;

use Aurora\Collection;
use Aurora\View;

class LengthAwarePaginator extends AbstractPaginator implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * The total number of items before slicing.
     */
    protected int $total;

    /**
     * The last available page.
     */
    protected int $lastPage;

    /**
     * Create a new paginator instance.
     *
     * @param array $options (path, query, fragment, pageName)
     */
    public function __construct(Collection|array $items, int $total, int $perPage, ?int $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total = $total;
        $this->perPage = $perPage;
        $this->lastPage = max((int)ceil($total / $perPage), 1);
        $this->path = '/' !== $this->path ? rtrim($this->path, '/') : $this->path;
        $this->currentPage = $this->setCurrentPage($currentPage, $this->pageName);
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
    }

    /**
     * Render the paginator using the given view.
     */
    public function links(?string $view = null, array $data = []): string
    {
        return $this->render($view, $data);
    }

    /**
     * Render the paginator using the given view.
     *
     * @param ?string $view
     */
    public function render(?string $view = null, array $data = []): string
    {
        return View::make($view ?: static::$defaultView, array_merge($data, [
            'paginator' => $this,
            'elements' => $this->elements(),
        ]))->render();
    }

    /**
     * Get the total number of items being paginated.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Determine if there are more items in the data source.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Get the URL for the next page.
     */
    public function nextPageUrl(): ?string
    {
        if ($this->lastPage() > $this->currentPage()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    /**
     * Get the last page.
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get the current page for the request.
     */
    protected function setCurrentPage(int $currentPage, string $pageName): int
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage($pageName);

        return $this->isValidPageNumber($currentPage) ? $currentPage : 1;
    }

    /**
     * Get the array of elements to pass to the view.
     */
    protected function elements(): array
    {
        $window = UrlWindow::make($this);

        return array_filter([
            $window['first'],
            \is_array($window['slider']) ? '...' : null,
            $window['slider'],
            \is_array($window['last']) ? '...' : null,
            $window['last'],
        ]);
    }
}
