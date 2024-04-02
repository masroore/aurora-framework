<?php

namespace Aurora;

use Aurora\Profiling\Profiler;

class Event
{
    /**
     * All of the registered events.
     */
    public static array $events = [];

    /**
     * The wildcard events.
     */
    public static array $wildcards = [];

    /**
     * The sorted event events.
     */
    public static array $sorted = [];

    /**
     * The queued events waiting for flushing.
     */
    public static array $queued = [];

    /**
     * All of the registered queue flusher callbacks.
     */
    public static array $flushers = [];

    /**
     * Override all callbacks for a given event with a new callback.
     */
    public static function override(string $event, $callback, $priority = 0): void
    {
        static::clear($event);

        static::listen($event, $callback, $priority);
    }

    /**
     * Clear all event listeners for a given event.
     */
    public static function clear(string $event): void
    {
        unset(static::$events[$event], static::$sorted[$event]);
    }

    /**
     * Register a callback for a given event.
     *
     * <code>
     *        // Register a callback for the "start" event
     *        Event::listen('start', function() {return 'Started!';});
     *
     *        // Register an object instance callback for the given event
     *        Event::listen('event', array($object, 'method'));
     *
     *        //Register same callback with multiple events
     *        Event::listen(array('start', 'over'), function(){return 'ring';});
     *
     * </code>
     */
    public static function listen(array|string $events, $callback, int $priority = 0): void
    {
        foreach ((array)$events as $event) {
            if (str_contains($event, '*')) {
                static::setupWildcardListen($event, $callback);
            } else {
                static::$events[$event][$priority][] = $callback;

                unset(static::$sorted[$event]);
            }
        }
    }

    /**
     * Setup a wildcard listener callback.
     */
    public static function setupWildcardListen(string $event, $listener): void
    {
        static::$wildcards[$event][] = $listener;
    }

    /**
     * Add an item to an event queue for processing.
     */
    public static function queue(string $queue, string $key, array $data = []): void
    {
        static::$queued[$queue][$key] = $data;
    }

    /**
     * Register a queue flusher callback.
     */
    public static function flusher(string $queue, $callback): void
    {
        static::$flushers[$queue][] = $callback;
    }

    /**
     * Fire an event and return the first response.
     *
     * <code>
     *        // Fire the "start" event
     *        $response = Event::first('start');
     *
     *        // Fire the "start" event passing an array of parameters
     *        $response = Event::first('start', array('Aurora', 'Framework'));
     * </code>
     */
    public static function first(string $event, array $parameters = [])
    {
        return head(static::fire($event, $parameters));
    }

    /**
     * Fire an event so that all listeners are called.
     *
     * <code>
     *        // Fire the "start" event
     *        $responses = Event::fire('start');
     *
     *        // Fire the "start" event passing an array of parameters
     *        $responses = Event::fire('start', array('Aurora', 'Framework'));
     *
     *        // Fire multiple events with the same parameters
     *        $responses = Event::fire(array('start', 'loading'), $parameters);
     * </code>
     *
     * @return array
     */
    public static function fire(array|string $events, array $parameters = [], bool $halt = false): mixed
    {
        $responses = [];

        // If the event has listeners, we will simply iterate through them and call
        // each listener, passing in the parameters. We will add the responses to
        // an array of event responses and return the array.
        foreach ((array)$events as $event) {
            foreach (static::getListeners($event) as $callback) {
                // add profile to watch event
                // TODO not hard coding profiler here
                Profiler::event($event, param_str($parameters));

                $response = \call_user_func_array($callback, $parameters);

                // If the event is set to halt, we will return the first response
                // that is not null. This allows the developer to easily stack
                // events but still get the first valid response.
                if ($halt && null !== $response) {
                    return $response;
                }

                // After the handler has been called, we'll add the response to
                // an array of responses and return the array to the caller so
                // all of the responses can be easily examined.
                $responses[] = $response;
            }
        }

        return $halt ? null : $responses;
    }

    /**
     * Get all of the listeners for a given event name.
     */
    public static function getListeners(string $eventName): array
    {
        $wildcards = static::getWildcardListeners($eventName);

        if (!isset(static::$sorted[$eventName])) {
            static::sortListeners($eventName);
        }

        return array_merge(static::$sorted[$eventName], $wildcards);
    }

    /**
     * Get the wildcard listeners for the event.
     */
    public static function getWildcardListeners(string $eventName): array
    {
        $wildcards = [];

        foreach (static::$wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }

        return $wildcards;
    }

    /**
     * Sort the listeners for a given event by priority.
     */
    public static function sortListeners(string $eventName): void
    {
        static::$sorted[$eventName] = [];

        // If listeners exist for the given event, we will sort them by the priority
        // so that we can call them in the correct order. We will cache off these
        // sorted event listeners so we do not have to re-sort on every events.
        if (isset(static::$events[$eventName])) {
            krsort(static::$events[$eventName]);

            static::$sorted[$eventName] = \call_user_func_array('array_merge', static::$events[$eventName]);
        }
    }

    /**
     * Determine if an event has any registered listeners.
     */
    public static function listeners(string $event): bool
    {
        return isset(static::$events[$event]);
    }

    /**
     * Fire an event and return the first response.
     *
     * Execution will be halted after the first valid response is found.
     */
    public static function until(string $event, array $parameters = []): mixed
    {
        return static::fire($event, $parameters, true);
    }

    /**
     * Flush an event queue, firing the flusher for each payload.
     */
    public static function flush(string $queue): void
    {
        foreach (static::$flushers[$queue] as $flusher) {
            // We will simply spin through each payload registered for the event and
            // fire the flusher, passing each payloads as we go. This allows all
            // the events on the queue to be processed by the flusher easily.
            if (!isset(static::$queued[$queue])) {
                continue;
            }

            foreach (static::$queued[$queue] as $key => $payload) {
                array_unshift($payload, $key);

                \call_user_func_array($flusher, $payload);
            }
        }
    }
}
