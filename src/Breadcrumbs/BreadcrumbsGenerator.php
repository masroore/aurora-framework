<?php

namespace Aurora\Breadcrumbs;

class BreadcrumbsGenerator
{
    protected $callbacks = [];

    protected $breadcrumbs = [];

    public function __construct(array $callbacks)
    {
        $this->callbacks = $callbacks;
    }

    public function get()
    {
        return $this->breadcrumbs;
    }

    public function set(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * @param string $name
     */
    public function call($name, array $args = []): void
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException("Invalid breadcrumb: $name");
        }

        if (!\is_array($args)) {
            $args = \array_slice(\func_get_args(), 1);
        }

        array_unshift($args, $this);

        \call_user_func_array($this->callbacks[$name], $args);
    }

    public function parent($name, $args = []): void
    {
        if (!\is_array($args)) {
            $args = \array_slice(\func_get_args(), 1);
        }

        $this->call($name, $args);
    }

    public function push($title, $url = null): void
    {
        $this->breadcrumbs[] = (object)[
            'title' => $title,
            'url' => $url,
            // These will be altered later where necessary:
            'first' => false,
            'last' => false,
        ];
    }

    public function toArray()
    {
        $breadcrumbs = $this->breadcrumbs;

        // Add first & last indicators
        if ($breadcrumbs) {
            $breadcrumbs[0]->first = true;
            $breadcrumbs[\count($breadcrumbs) - 1]->last = true;
        }

        return $breadcrumbs;
    }
}
