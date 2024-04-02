<?php

namespace Aurora\ResponseCache;

use Aurora\HTMLMin;
use Aurora\Response;
use Aurora\Traits\SerializerTrait;

class ResponseSerializer
{
    use SerializerTrait;

    /**
     * Serialize a response.
     *
     * @param bool $minify
     *
     * @return string
     */
    public function dump(Response $response, $minify = false)
    {
        $c = $response->render();
        if ($minify) {
            $c = HTMLMin::html($c);
        }
        $n = $response->foundation->getStatusCode();
        $h = $response->foundation->headers;

        return $this->serialize(compact('c', 'n', 'h'));
    }

    /**
     * Unserialize a response.
     *
     * @return Response
     */
    public function load($serializedResponse)
    {
        $responseProperties = $this->unserialize($serializedResponse);
        $response = new Response($responseProperties['c'], $responseProperties['n']);
        $response->getFoundation()->headers = $responseProperties['h'];
        $response->setDoNotCache(true);

        return $response;
    }
}
