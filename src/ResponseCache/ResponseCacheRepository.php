<?php

namespace Aurora\ResponseCache;

use Aurora\Response;

class ResponseCacheRepository
{
    /** @var Drivers\Driver */
    protected $storage;

    /** @var ResponseSerializer */
    protected $responseSerializer;

    /** @var string */
    protected $cacheStoreName;

    /** @var bool */
    private $minify;

    public function __construct(ResponseSerializer $responseSerializer)
    {
        $this->cacheStoreName = config('responsecache.cache_store');
        $this->minify = (bool)config('responsecache.minify', false);
        $this->storage = $this->factory($this->cacheStoreName);
        $this->storage->setPrefix('r_')->setSuffix('.resp');
        $this->responseSerializer = $responseSerializer;
    }

    /**
     * @param string   $key
     * @param Response $response
     * @param int      $minutes
     */
    public function put($key, $response, $minutes): void
    {
        $this->storage->put($key, $this->responseSerializer->dump($response, $this->minify), $minutes);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->storage->has($key);
    }

    /**
     * @param string $key
     *
     * @return Response
     */
    public function get($key)
    {
        return $this->responseSerializer->load($this->storage->get($key));
    }

    public function flush(): void
    {
        $this->storage->flush();
    }

    /**
     * @return Drivers\File|Drivers\Memcache|Drivers\Memcached|Drivers\Redis
     */
    protected function factory($driver)
    {
        switch ($driver) {
            case 'file':
                return new Drivers\File(STORAGE_PATH . DS);

            case 'memcache':
                return new Drivers\Memcache(Memcache::connection(), config('responsecache.key'));

            case 'memcached':
                return new Drivers\Memcached(Memcached::connection(), config('responsecache.key'));

            case 'redis':
                return new Drivers\Redis(\Redis::connection());

            default:
                throw new \Exception("ResponseCache driver {$driver} is not supported.");
        }
    }
}
