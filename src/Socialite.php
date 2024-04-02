<?php

namespace Aurora;

use Aurora\Socialite\SocialiteManager;

class Socialite
{
    /**
     * @var SocialiteManager
     */
    private static $socialiteManager;

    public static function __callStatic($method, $parameters)
    {
        if (null === self::$socialiteManager) {
            self::boot();
        }

        return \call_user_func_array([self::$socialiteManager, $method], $parameters);
    }

    public static function boot(): void
    {
        $config = array_merge(config('socialite', []), config('services', []));
        self::$socialiteManager = new SocialiteManager($config);
    }
}
