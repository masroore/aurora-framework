<?php

namespace Aurora;

use Aurora\ResponseCache\ResponseCache as C;
use Aurora\Routing\Route;

class ResponseCache
{
    private static $instance;

    /**
     * @param string $method
     */
    public static function __callStatic($method, array $arguments)
    {
        return \call_user_func_array([static::getInstance(), $method], $arguments);
    }

    public static function filters(): void
    {
        Route::filter('before', static function () {
            if (self::hasCached()) {
                return self::getCachedResponse();
            }
        });

        Route::filter('after', static function ($response): void {
            if (self::shouldCache($response)) {
                self::cacheResponse($response);
            }
        });
    }

    /**
     * @return C
     */
    private static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = C::make();
        }

        return static::$instance;
    }
}
