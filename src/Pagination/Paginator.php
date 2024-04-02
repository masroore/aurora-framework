<?php

namespace Aurora\Pagination;

use Aurora\Collection;
use Aurora\View;

class Paginator extends AbstractPaginator implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * Determine if there are more items in the data source.
     */
    protected bool $hasMore;

    /**
     * Create a new paginator instance.
     *
     * @param array $options (path, query, fragment, pageName)
     */
    public function __construct(Collection|array $items, int $perPage, ?int $currentPage = null, array $options = [])
    {
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage);
        $this->path = '/' !== $this->path ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
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
     */
    public function render(?string $view = null, array $data = []): string
    {
        return View::make($view ?: static::$defaultSimpleView, array_merge($data, [
            'paginator' => $this,
        ]))->render();
    }

    /**
     * Manually indicate that the paginator does have more pages.
     *
     * @return $this
     */
    public function hasMorePagesWhen(bool $hasMore = true): static
    {
        $this->hasMore = $hasMore;

        return $this;
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
        ];
    }

    /**
     * Get the URL for the next page.
     */
    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    /**
     * Determine if there are more items in the data source.
     */
    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    /**
     * Get the current page for the request.
     */
    protected function setCurrentPage(int $currentPage): int
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage();

        return $this->isValidPageNumber($currentPage) ? (int)$currentPage : 1;
    }

    /**
     * Set the items for the paginator.
     */
    protected function setItems(Collection|array $items): void
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);

        $this->hasMore = $this->items->count() > $this->perPage;

        $this->items = $this->items->slice(0, $this->perPage);
    }
}
