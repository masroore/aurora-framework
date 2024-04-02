<?php

namespace Aurora\Profiling;

use Aurora\Config;
use Aurora\Database;
use Aurora\Event;
use Aurora\Request;
use Aurora\Response;

class Profiler
{
    /**
     * An array of the recorded Profiler data.
     *
     * @var array
     */
    protected static $data = ['queries' => [], 'logs' => [], 'timers' => [], 'files' => [], 'events' => []];

    /**
     * Allow a callback to be timed.
     *
     * @param string $name
     */
    public static function time(\Closure $func, $name = 'default_func_timer'): void
    {
        // First measure the runtime of the func
        $start = microtime(true);
        $func();
        $end = microtime(true);

        // Check to see if a timer by that name exists
        if (isset(static::$data['timers'][$name])) {
            $name .= uniqid('', false);
        }

        // Push the time into the timers array for display
        static::$data['timers'][$name]['start'] = $start;
        static::$data['timers'][$name]['end'] = $end;
        static::$data['timers'][$name]['time'] = number_format(($end - $start) * 1000, 2);
    }

    /**
     *  Start, or add a tick to a timer.
     *
     * @param string   $name
     * @param callable $callback
     */
    public static function tick($name = 'default_timer', $callback = null): void
    {
        $name = trim($name);
        if (empty($name)) {
            $name = 'default_timer';
        }

        // Is this a brand new tick?
        if (isset(static::$data['timers'][$name])) {
            $current_timer = static::$data['timers'][$name];
            $ticks = \count($current_timer['ticks']);

            // Initialize the new time for the tick
            $new_tick = [];
            $mt = microtime(true);
            $new_tick['raw_time'] = $mt - $current_timer['start'];
            $new_tick['time'] = number_format(($mt - $current_timer['start']) * 1000, 2);

            // Use either the start time or the last tick for the diff
            if ($ticks > 0) {
                $last_tick = $current_timer['ticks'][$ticks - 1]['raw_time'];
                $new_tick['diff'] = number_format(($new_tick['raw_time'] - $last_tick) * 1000, 2);
            } else {
                $new_tick['diff'] = $new_tick['time'];
            }

            // Add the new tick to the stack of them
            static::$data['timers'][$name]['ticks'][] = $new_tick;
        } else {
            // Initialize a start time on the first tick
            static::$data['timers'][$name]['start'] = microtime(true);
            static::$data['timers'][$name]['ticks'] = [];
        }

        // Run the callback for this tick if it's specified
        if (null !== $callback && \is_callable($callback)) {
            // After we've ticked, call the callback function
            \call_user_func($callback, static::$data['timers'][$name]);
        }
    }

    /**
     * Attach the Profiler's event listeners.
     */
    public static function attach(): void
    {
        // First we'll attach to the query and log events. These allow us to catch
        // all of the SQL queries and log messages that come through Aurora,
        // and we will pass them onto the Profiler for simple storage.
        Event::listen('aurora.log', static function ($type, $message): void {
            self::log($type, $message);
        });

        Event::listen('aurora.query', static function ($sql, $bindings, $time): void {
            self::query($sql, $bindings, $time);
        });

        // We'll attach the profiler to the "done" event so that we can easily
        // attach the profiler output to the end of the output sent to the
        // browser. This will display the profiler's nice toolbar.
        Event::listen('aurora.done', static function ($response): void {
            echo self::render($response);
        });
    }

    /**
     * Add a log entry to the log entries array.
     *
     * @param string $type
     * @param string $message
     */
    public static function log($type, $message): void
    {
        static::$data['logs'][] = [$type, $message];
    }

    /**
     * Add a performed SQL query to the Profiler.
     *
     * @param string $sql
     * @param array  $bindings
     * @param float  $time
     */
    public static function query($sql, $bindings, $time): void
    {
        foreach ($bindings as $binding) {
            $binding = Database::escape($binding);

            $sql = preg_replace('/\?/', $binding, $sql, 1);
            $sql = htmlspecialchars($sql, \ENT_QUOTES, 'UTF-8', false);
        }

        static::$data['queries'][] = [$sql, $time];
    }

    /**
     * Get the rendered contents of the Profiler.
     *
     * @return string
     */
    public static function render(Response $response)
    {
        // We only want to send the profiler toolbar if the request is not an AJAX
        // request, as sending it on AJAX requests could mess up JSON driven API
        // type applications, so we will not send anything in those scenarios.
        if (!Request::ajax() && Config::get('app.profiler')) {
            static::$data['memory'] = get_file_size(memory_get_usage(true));
            static::$data['memory_peak'] = get_file_size(memory_get_peak_usage(true));
            static::$data['time'] = number_format((microtime(true) - AURORA_START) * 1000, 2);
            foreach (static::$data['timers'] as &$timer) {
                $timer['running_time'] = number_format((microtime(true) - $timer['start']) * 1000, 2);
            }

            static::getIncludedFiles();

            return render('path: ' . __DIR__ . '/template' . BLADE_EXT, static::$data);
        }
    }

    /**
     * Add an event log.
     *
     * @param string $name
     * @param string $params
     */
    public static function event($name, $params = null): void
    {
        static::$data['events'][] = [$name, $params];
    }

    /**
     * Get all of the files that have been included.
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        // We'll cache this internally to avoid running this
        // multiple times.
        if (empty(static::$data['files'])) {
            $files = get_included_files();

            foreach ($files as $filePath) {
                $size = get_file_size(filesize($filePath));

                static::$data['files'][] = compact('filePath', 'size');
            }
        }

        return static::$data['files'];
    }
}
