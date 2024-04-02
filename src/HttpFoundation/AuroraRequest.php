<?php

namespace Aurora\HttpFoundation;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

class AuroraRequest extends Request
{
    /**
     * Creates a new request with values from PHP's super globals.
     *
     * @return AuroraRequest A new request
     */
    public static function createFromGlobals(): static
    {
        $request = new static($_GET, $_POST, [], $_COOKIE, $_FILES, $_SERVER);

        if ((str_starts_with($request->server->get('CONTENT_TYPE', ''), 'application/x-www-form-urlencoded')
                || str_starts_with($request->server->get('HTTP_CONTENT_TYPE', ''), 'application/x-www-form-urlencoded'))
            && \in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'DELETE', 'PATCH'], true)
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new InputBag($data);
        }

        return $request;
    }

    /**
     * Get the root URL of the application.
     */
    public function getRootUrl(): string
    {
        return $this->getScheme() . '://' . $this->getHttpHost() . $this->getBasePath();
    }
}
