<?php

namespace Aurora\CLI;

use Aurora\Bundle;
use Aurora\IoC;
use Aurora\Str;

class Command
{
    /**
     * Run a CLI task with the given arguments.
     *
     * <code>
     *        // Call the migrate artisan task
     *        Command::run(array('migrate'));
     *
     *        // Call the migrate task with some arguments
     *        Command::run(array('migrate:rollback', 'bundle-name'))
     * </code>
     *
     * @param array $arguments
     */
    public static function run($arguments = []): void
    {
        static::validate($arguments);

        [$bundle, $task, $method] = static::parse($arguments[0]);

        // If the task exists within a bundle, we will start the bundle so that any
        // dependencies can be registered in the application IoC container. If the
        // task is registered in the container,  we'll resolve it.
        if (Bundle::exists($bundle)) {
            Bundle::start($bundle);
        }

        $task = static::resolve($bundle, $task);

        // Once the bundle has been resolved, we'll make sure we could actually
        // find that task, and then verify that the method exists on the task
        // so we can successfully call it without a problem.
        if (null === $task) {
            throw new \Exception("Sorry, I can't find that task.");
        }

        if (\is_callable([$task, $method])) {
            $task->$method(\array_slice($arguments, 1));
        } else {
            throw new \Exception("Sorry, I can't find that method!");
        }
    }

    /**
     * Resolve an instance of the given task name.
     *
     * <code>
     *        // Resolve an instance of a task
     *        $task = Command::resolve('application', 'migrate');
     *
     *        // Resolve an instance of a task within a bundle
     *        $task = Command::resolve('bundle', 'foo');
     * </code>
     *
     * @param string $bundle
     * @param string $task
     *
     * @return object
     */
    public static function resolve($bundle, $task)
    {
        $identifier = Bundle::identifier($bundle, $task);

        // First we'll check to see if the task has been registered in the
        // application IoC container. This allows all dependencies to be
        // injected into tasks for more flexible testability.
        if (IoC::registered("task: {$identifier}")) {
            return IoC::resolve("task: {$identifier}");
        }

        // If the task file exists, we'll format the bundle and task name
        // into a task class name and resolve an instance of the class so that
        // the requested method may be executed.
        if (file_exists($path = Bundle::path($bundle) . 'tasks/' . $task . EXT)) {
            require_once $path;

            $task = static::format($bundle, $task);

            return new $task();
        }
    }

    /**
     * Parse the command line arguments and return the results.
     *
     * @param array $argv
     *
     * @return array
     */
    public static function options($argv)
    {
        $options = [];

        $arguments = [];

        for ($i = 0, $count = \count($argv); $i < $count; ++$i) {
            $argument = $argv[$i];

            // If the CLI argument starts with a double hyphen, it is an option,
            // so we will extract the value and add it to the array of options
            // to be returned by the method.
            if (Str::startsWith($argument, '--')) {
                // By default, we will assume the value of the options is true,
                // but if the option contains an equals sign, we will take the
                // value to the right of the equals sign as the value and
                // remove the value from the option key.
                [$key, $value] = [mb_substr($argument, 2), true];

                if (false !== ($equals = mb_strpos($argument, '='))) {
                    $key = mb_substr($argument, 2, $equals - 2);

                    $value = mb_substr($argument, $equals + 1);
                }

                $options[$key] = $value;
            }
            // If the CLI argument does not start with a double hyphen it's
            // simply an argument to be passed to the console task so we'll
            // add it to the array of "regular" arguments.
            else {
                $arguments[] = $argument;
            }
        }

        return [$arguments, $options];
    }

    /**
     * Determine if the given command arguments are valid.
     *
     * @param array $arguments
     */
    protected static function validate($arguments): void
    {
        if (!isset($arguments[0])) {
            throw new \Exception('You forgot to provide the task name.');
        }
    }

    /**
     * Parse the task name to extract the bundle, task, and method.
     *
     * @param string $task
     *
     * @return array
     */
    protected static function parse($task)
    {
        [$bundle, $task] = Bundle::parse($task);

        // Extract the task method from the task string. Methods are called
        // on tasks by separating the task and method with a single colon.
        // If no task is specified, "run" is used as the default.
        if (str_contains($task, ':')) {
            [$task, $method] = explode(':', $task);
        } else {
            $method = 'run';
        }

        return [$bundle, $task, $method];
    }

    /**
     * Format a bundle and task into a task class name.
     *
     * @param string $bundle
     * @param string $task
     *
     * @return string
     */
    protected static function format($bundle, $task)
    {
        $prefix = Bundle::classPrefix($bundle);

        return '\\' . $prefix . Str::classify($task) . '_Task';
    }
}
