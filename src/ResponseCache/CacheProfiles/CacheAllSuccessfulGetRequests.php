<?php

namespace Aurora\ResponseCache\CacheProfiles;

use Aurora\Request;
use Aurora\Response;

class CacheAllSuccessfulGetRequests extends BaseCacheProfile implements ICacheProfile
{
    /**
     * Determine if the given request should be cached;.
     *
     * @return bool
     */
    public function shouldCacheRequest()
    {
        return (Request::ajax() || $this->isRunningInConsole()) ? false : 0 === strcasecmp(Request::method(), 'GET');
    }

    /**
     * Determine if the given response should be cached.
     *
     * @return bool
     */
    public function shouldCacheResponse(Response $response)
    {
        return $response->foundation->isSuccessful();
    }
}
