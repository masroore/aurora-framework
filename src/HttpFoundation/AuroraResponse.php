<?php

namespace Aurora\HttpFoundation;

use Symfony\Component\HttpFoundation\Response;

/**
 * Response represents an HTTP response.
 *
 * @api
 */
class AuroraResponse extends Response
{
    /**
     * Finishes the request for PHP-FastCGI.
     */
    public function finish(): void
    {
        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (\function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            static::closeOutputBuffers(0, true);
            flush();
        }
    }
}
