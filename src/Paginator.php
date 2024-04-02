<?php

namespace Aurora;

use Aurora\Pagination\AbstractPaginator;

class Paginator
{
    public static function __callStatic($method, array $parameters)
    {
        return \call_user_func_array([AbstractPaginator::class, $method], $parameters);
    }

    public static function boot(): void
    {
        AbstractPaginator::currentPathResolver(static fn () => Request::uri());

        AbstractPaginator::currentPageResolver(static fn ($pageName = 'page') => self::getCurrentPage($pageName));
    }

    /**
     * Create a new LengthAwarePaginator instance.
     *
     * @param int        $total
     * @param int        $perPage
     * @param mixed|null $currentPage
     *
     * @return AbstractPaginator
     */
    public static function paginator(array $results, $total, $perPage, $currentPage = null, array $options = [])
    {
        return new Pagination\LengthAwarePaginator($results, $total, $perPage, $currentPage, $options);
    }

    /**
     * @param string   $pageName
     * @param int|null $page
     *
     * @return int
     */
    public static function getCurrentPage($pageName = 'page', $page = null)
    {
        if (null === $page) {
            $page = Input::get($pageName);
        }

        if (false !== filter_var($page, \FILTER_VALIDATE_INT) && (int)$page >= 1) {
            return (int)$page;
        }

        return 1;
    }

    /**
     * Create a new Paginator instance.
     *
     * @param int      $perPage
     * @param int|null $currentPage
     *
     * @return AbstractPaginator
     */
    public static function simplePaginator(array $results, $perPage, $currentPage = null, array $options = [])
    {
        return new Pagination\Paginator($results, $perPage, $currentPage, $options);
    }
}
