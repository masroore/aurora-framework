<?php

namespace Aurora\ResponseCache\CacheProfiles;

use Aurora\Auth;
use Aurora\Config;
use Aurora\Request;
use Carbon\Carbon;

abstract class BaseCacheProfile
{
    private $cacheLifeTime;

    /**
     * Return the time when the cache must be invalided.
     *
     * @return \DateTime
     */
    public function cacheRequestUntil()
    {
        return Carbon::now()->addMinutes($this->cacheLifeTime());
    }

    /**
     * @return int
     */
    public function cacheLifeTime()
    {
        if (null === $this->cacheLifeTime) {
            $this->cacheLifeTime = (int)Config::get('responsecache.cache_lifetime', 60);
        }

        return $this->cacheLifeTime;
    }

    /**
     * Set a string to add to differentiate this request from others.
     *
     * @return string
     */
    public function cacheNameSuffix()
    {
        return Auth::check() ? Auth::user()->id : '';
    }

    /**
     * Determine if the app is running in the console.
     *
     * To allow testing this will return false the environment is testing.
     *
     * @return bool
     */
    public function isRunningInConsole()
    {
        return Request::cli();
    }
}
