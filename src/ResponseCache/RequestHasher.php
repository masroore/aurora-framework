<?php

namespace Aurora\ResponseCache;

use Aurora\Request;
use Aurora\ResponseCache\CacheProfiles\ICacheProfile;

class RequestHasher
{
    protected $cacheProfile;

    public function __construct(ICacheProfile $cacheProfile)
    {
        $this->cacheProfile = $cacheProfile;
    }

    /**
     * Get a hash value for the given request.
     *
     * @return string
     */
    public function getHash()
    {
        $key = sprintf(
            '%s$$%s$$%s',
            mb_strtoupper(Request::method()),
            Request::getUri(),
            $this->cacheProfile->cacheNameSuffix()
        );

        return 'responsecache-' . md5($key);
    }
}
