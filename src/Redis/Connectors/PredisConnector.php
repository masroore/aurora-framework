<?php

namespace Aurora\Redis\Connectors;

use Aurora\Arr;
use Aurora\Redis\Connections\PredisClusterConnection;
use Aurora\Redis\Connections\PredisConnection;
use Predis\Client;

class PredisConnector
{
    /**
     * Create a new clustered Predis connection.
     *
     * @return PredisConnection
     */
    public function connect(array $config, array $options)
    {
        $formattedOptions = array_merge(
            ['timeout' => 10.0],
            $options,
            Arr::pull($config, 'options', [])
        );

        return new PredisConnection(new Client($config, $formattedOptions));
    }

    /**
     * Create a new clustered Predis connection.
     *
     * @return PredisClusterConnection
     */
    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        $clusterSpecificOptions = Arr::pull($config, 'options', []);

        return new PredisClusterConnection(new Client(array_values($config), array_merge(
            $options,
            $clusterOptions,
            $clusterSpecificOptions
        )));
    }
}
