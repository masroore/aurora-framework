<?php

namespace Aurora\ResponseCache\CacheProfiles;

use Aurora\Response;

interface ICacheProfile
{
    /**
     * Determine if the given request should be cached.
     *
     * @return bool
     */
    public function shouldCacheRequest();

    /**
     * Determine if the given response should be cached.
     *
     * @return bool
     */
    public function shouldCacheResponse(Response $response);

    /**
     * Return the time when the cache must be invalidated.
     *
     * @return \DateTime
     */
    public function cacheRequestUntil();

    /**
     * Return a string to differentiate this request from others.
     *
     * For example: if you want a different cache per user you could return the id of
     * the logged in user.
     *
     * @return string
     */
    public function cacheNameSuffix();

    /**
     * @return int
     */
    public function cacheLifeTime();
}
