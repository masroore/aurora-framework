<?php

namespace Aurora;

class TrustProxies
{
    /**
     * The trusted proxies for the application.
     *
     * @var array|string|null
     */
    private static $proxies;

    /**
     * The proxy header mappings.
     *
     * @var int|string|null
     */
    private static $headers;

    /**
     * Handle an incoming request.
     */
    public static function handle(\Closure $next)
    {
        \Request::setTrustedProxies([], static::getTrustedHeaderNames()); // Reset trusted proxies between requests
        static::setTrustedProxyIpAddresses();

        return $next();
    }

    /**
     * Sets the trusted proxies on the request to the value of trustedproxy.proxies.
     */
    private static function setTrustedProxyIpAddresses(): void
    {
        $trustedIps = static::$proxies ?: \Config::get('trustedproxy.proxies');

        // Trust any IP address that calls us
        // `**` for backwards compatibility, but is deprecated
        if ('*' === $trustedIps || '**' === $trustedIps) {
            static::setTrustedProxyIpAddressesToTheCallingIp();
        } else {
            // Support IPs addresses separated by comma
            $trustedIps = \is_string($trustedIps) ?
                array_map('trim', explode(',', $trustedIps)) :
                $trustedIps;

            // Only trust specific IP addresses
            if (\is_array($trustedIps)) {
                static::setTrustedProxyIpAddressesToSpecificIps($trustedIps);
            }
        }
    }

    /**
     * Specify the IP addresses to trust explicitly.
     *
     * @param array $trustedIps
     */
    private static function setTrustedProxyIpAddressesToSpecificIps($trustedIps): void
    {
        \Request::setTrustedProxies((array)$trustedIps, static::getTrustedHeaderNames());
    }

    /**
     * Set the trusted proxy to be the IP address calling this servers.
     */
    private static function setTrustedProxyIpAddressesToTheCallingIp(): void
    {
        \Request::setTrustedProxies([\Request::server('REMOTE_ADDR')], static::getTrustedHeaderNames());
    }

    /**
     * Retrieve trusted header name(s), falling back to defaults if config not set.
     *
     * @return int a bit field of Request::HEADER_*, to set which headers to trust from your proxies
     */
    private static function getTrustedHeaderNames()
    {
        $headers = self::$headers ?: \Config::get('trustedproxy.headers');
        switch ($headers) {
            case 'HEADER_X_FORWARDED_AWS_ELB':
            case Request::HEADER_X_FORWARDED_AWS_ELB:
                return Request::HEADER_X_FORWARDED_AWS_ELB;

                break;
            case 'HEADER_FORWARDED':
            case Request::HEADER_FORWARDED:
                return Request::HEADER_FORWARDED;

                break;
            default:
                return Request::HEADER_X_FORWARDED_ALL;
        }

        // Should never reach this point
        return $headers;
    }
}
