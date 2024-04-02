<?php

namespace Aurora\ResponseCache;

use Aurora\Request;
use Aurora\Response;
use Aurora\ResponseCache\CacheProfiles\ICacheProfile;

class ResponseCache
{
    /** @var ResponseCacheRepository */
    protected $cacheRepository;

    /** @var RequestHasher */
    protected $hasher;

    /** @var ICacheProfile */
    protected $cacheProfile;

    /** @var bool */
    private $enabled;

    public function __construct(ResponseCacheRepository $cacheRepository, RequestHasher $hasher, ICacheProfile $cacheProfile)
    {
        $this->cacheRepository = $cacheRepository;
        $this->hasher = $hasher;
        $this->cacheProfile = $cacheProfile;
        $this->enabled = (bool)config('responsecache.enabled', false);
    }

    /**
     * @return ResponseCache
     */
    public static function make(?ICacheProfile $cacheProfile = null)
    {
        if (null === $cacheProfile) {
            $cacheProfileClass = config('responsecache.cache_profile');
            $cacheProfile = new $cacheProfileClass();
        }

        return new self(
            new ResponseCacheRepository(new ResponseSerializer()),
            new RequestHasher($cacheProfile),
            $cacheProfile
        );
    }

    /**
     * Determine if the given request should be cached.
     *
     * @return bool
     */
    public function shouldCache(Response $response)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($response->getDoNotCache()) {
            return false;
        }

        if (Request::foundation()->attributes->has('cacheresponse.do_not_cache')) {
            return false;
        }

        if (!$this->cacheProfile->shouldCacheRequest()) {
            return false;
        }

        return $this->cacheProfile->shouldCacheResponse($response);
    }

    /**
     * Store the given response in the cache.
     */
    public function cacheResponse(Response $response): void
    {
        if (config('responsecache.add_cache_time_header')) {
            $response = $this->addCachedHeader($response);
        }

        $this->cacheRepository->put(
            $this->hasher->getHash(),
            $response,
            $this->cacheProfile->cacheLifeTime()
        );
    }

    /**
     * Determine if the given request has been cached.
     *
     * @return bool
     */
    public function hasCached()
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->cacheRepository->has($this->hasher->getHash());
    }

    /**
     * Get the cached response for the given request.
     *
     * @return Response
     */
    public function getCachedResponse()
    {
        return $this->cacheRepository->get($this->hasher->getHash());
    }

    /**
     *  Flush the cache.
     */
    public function flush(): void
    {
        $this->cacheRepository->flush();
    }

    /**
     * Add a header with the cache date on the response.
     *
     * @return Response
     */
    protected function addCachedHeader(Response $response)
    {
        $clonedResponse = clone $response;

        $clonedResponse->foundation->headers->set('Aurora-Response-Cache', date('c'));

        return $clonedResponse;
    }
}
