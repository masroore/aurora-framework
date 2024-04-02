<?php

namespace Aurora\Traits;

trait ForwardsCalls
{
    /**
     * Forward a method call to the given object.
     *
     * @param string $method
     * @param array  $parameters
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (\Error $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] !== $object::class
                || $matches['method'] !== $method) {
                throw $e;
            }

            static::throwBadMethodCallException($method);
        } catch (\BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] !== $object::class
                || $matches['method'] !== $method) {
                throw $e;
            }

            static::throwBadMethodCallException($method);
        }
    }

    /**
     * Throw a bad method call exception for the given method.
     *
     * @param string $method
     */
    protected static function throwBadMethodCallException($method): void
    {
        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
}
