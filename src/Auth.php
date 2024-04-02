<?php

namespace Aurora;

use Aurora\Routing\Route;

class Auth
{
    /**
     * The currently active authentication drivers.
     */
    public static array $drivers = [];

    /**
     * The third-party driver registrar.
     */
    public static array $registrar = [];

    /**
     * Magic Method for calling the methods on the default cache driver.
     *
     * <code>
     *        // Call the "user" method on the default auth driver
     *        $user = Auth::user();
     *
     *        // Call the "check" method on the default auth driver
     *        Auth::check();
     * </code>
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::driver(), $method], $parameters);
    }

    /**
     * Get an authentication driver instance.
     */
    public static function driver(?string $driver = null): Authentication\Drivers\Driver
    {
        if (null === $driver) {
            $driver = Config::get('auth.driver');
        }

        if (!isset(static::$drivers[$driver])) {
            static::$drivers[$driver] = static::factory($driver);
        }

        return static::$drivers[$driver];
    }

    /**
     * Register a third-party authentication driver.
     */
    public static function extend(string $driver, \Closure $resolver): void
    {
        static::$registrar[$driver] = $resolver;
    }

    /**
     * Register all authentication routes.
     */
    public static function routes(string $prefix = 'auth', string $as = 'auth.'): void
    {
        Route::group(compact('as', 'prefix'), static function (): void {
            Route::get('login', ['as' => 'login', 'uses' => 'auth@login']);
            Route::post('login', ['uses' => 'auth@login']);
            Route::get('logout', ['as' => 'logout', 'uses' => 'auth@logout']);

            Route::get('register', ['as' => 'register', 'uses' => 'auth@showRegistrationForm']);
            Route::post('register', ['uses' => 'auth@register']);

            Route::get('password/reset', ['as' => 'password.request', 'uses' => 'auth@showLinkRequestForm']);
            Route::post('password/email', ['as' => 'password.email', 'uses' => 'auth@sendResetLinkEmail']);
            Route::get('password/reset/(:any)', ['as' => 'password.reset', 'uses' => 'auth@showResetForm']);
            Route::post('password/reset', ['uses' => 'auth@reset']);
        });
    }

    /**
     * Create a new authentication driver instance.
     *
     * @throws \Exception
     */
    protected static function factory(string $driver): Authentication\Drivers\Fluent|Authentication\Drivers\Eloquent
    {
        if (isset(static::$registrar[$driver])) {
            $resolver = static::$registrar[$driver];

            return $resolver();
        }

        return match ($driver) {
            'fluent' => new Authentication\Drivers\Fluent(),
            'eloquent' => new Authentication\Drivers\Eloquent(),
            default => throw new \Exception("Auth driver {$driver} is not supported."),
        };
    }
}
