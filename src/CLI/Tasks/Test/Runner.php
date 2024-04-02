<?php

namespace Aurora\CLI\Tasks\Test;

use Aurora\Bundle;
use Aurora\CLI\Tasks\Task;
use Aurora\File;
use Aurora\Request;

class Runner extends Task
{
    /**
     * The base directory where the tests will be executed.
     *
     * A phpunit.xml should also be stored in that directory.
     *
     * @var string
     */
    protected $base_path;

    /**
     * Run all of the unit tests for the application.
     *
     * @param array $bundles
     */
    public function run($bundles = []): void
    {
        if (0 === \count($bundles)) {
            $bundles = [DEFAULT_BUNDLE];
        }

        $this->bundle($bundles);
    }

    /**
     * Run the tests for a given bundle.
     *
     * @param array $bundles
     */
    public function bundle($bundles = []): void
    {
        if (0 === \count($bundles)) {
            $bundles = Bundle::names();
        }

        $this->base_path = SYS_PATH . 'cli' . DS . 'tasks' . DS . 'test' . DS;

        foreach ($bundles as $bundle) {
            // To run PHPUnit for the application, bundles, and the framework
            // from one task, we'll dynamically stub PHPUnit.xml files via
            // the task and point the test suite to the correct directory
            // based on what was requested.
            if (is_dir($path = Bundle::path($bundle) . 'tests')) {
                $this->stub($path);

                $this->test();
            }
        }
    }

    /**
     * Run the tests for the Aurora framework.
     */
    public function core(): void
    {
        $this->base_path = SYS_PATH . 'tests' . DS;
        $this->stub(SYS_PATH . 'tests' . DS . 'cases');

        $this->test();
    }

    /**
     * Write a stub phpunit.xml file to the base directory.
     *
     * @param string $directory
     */
    protected function stub($directory): void
    {
        $path = SYS_PATH . 'cli/tasks/test/';

        $stub = File::get($path . 'stub.xml');

        // The PHPUnit bootstrap file contains several items that are swapped
        // at test time. This allows us to point PHPUnit at a few different
        // locations depending on what the developer wants to test.
        foreach (['bootstrap', 'directory'] as $item) {
            $stub = $this->{"swap_{$item}"}($stub, $directory);
        }

        File::put(path('base') . 'phpunit.xml', $stub);
    }

    /**
     * Run PHPUnit with the temporary XML configuration.
     */
    protected function test(): void
    {
        // We'll simply fire off PHPUnit with the configuration switch
        // pointing to our requested configuration file. This allows
        // us to flexibly run tests for any setup.
        $path = 'phpunit.xml';

        // fix the spaced directories problem when using the command line
        // strings with spaces inside should be wrapped in quotes.
        $esc_path = escapeshellarg($path);

        putenv('AURORA_ENV=' . Request::env());
        passthru('phpunit --configuration ' . $esc_path, $status);

        @unlink($path);

        // Pass through the exit status
        exit($status);
    }

    /**
     * Swap the bootstrap file in the stub.
     *
     * @param string $stub
     * @param string $directory
     *
     * @return string
     */
    protected function swap_bootstrap($stub, $directory)
    {
        return str_replace('{{bootstrap}}', $this->base_path . 'phpunit.php', $stub);
    }

    /**
     * Swap the directory in the stub.
     *
     * @param string $stub
     * @param string $directory
     *
     * @return string
     */
    protected function swap_directory($stub, $directory)
    {
        return str_replace('{{directory}}', $directory, $stub);
    }
}
