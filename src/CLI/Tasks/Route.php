<?php

namespace Aurora\CLI\Tasks;

use Aurora\Request;
use Aurora\Routing\Router;
use splitbrain\phpcli\TableFormatter;

class Route extends Task
{
    /**
     * Execute a route and dump the result.
     *
     * @param array $arguments
     */
    public function call($arguments = []): void
    {
        if (2 !== \count($arguments)) {
            throw new \Exception('Please specify a request method and URI.');
        }

        // First we'll set the request method and URI in the $_SERVER array,
        // which will allow the framework to retrieve the proper method
        // and URI using the URI and Request classes.
        $_SERVER['REQUEST_METHOD'] = mb_strtoupper($arguments[0]);

        $_SERVER['REQUEST_URI'] = $arguments[1];

        $this->route();

        echo \PHP_EOL;
    }

    public function all(): void
    {
        $tf = new TableFormatter();
        $tf->setBorder(' | ');

        // header
        echo $tf->format(
            ['8%', '40%', '15%', '10%', '10%', '10%', '2%'],
            ['Method', 'Route', 'Name', 'Prefix', 'Before', 'After', 'HTTPS']
        );

        // divider
        echo str_pad('', $tf->getMaxWidth(), '-') . \PHP_EOL;

        foreach (Router::routes() as $method => $routes) {
            foreach ($routes as $route => $param) {
                $as = $param['as'] ?? '';
                $prefix = $param['prefix'] ?? '';
                $before = $param['before'] ?? '';
                $after = $param['after'] ?? '';
                $https = $param['https'] ? '+' : '-';

                echo $tf->format(
                    ['8%', '40%', '15%', '10%', '10%', '10%', '2%'],
                    [$method, $route, $as, $prefix, $before, $after, $https]
                );
            }
        }
    }

    /**
     * Dump the results of the currently established route.
     */
    protected function route(): void
    {
        // We'll call the router using the method and URI specified by
        // the developer on the CLI. If a route is found, we will not
        // run the filters, but simply dump the result.
        $route = Router::route(Request::method(), $_SERVER['REQUEST_URI']);

        if (null !== $route) {
            var_dump($route->response());
        } else {
            echo '404: Not Found';
        }
    }
}
