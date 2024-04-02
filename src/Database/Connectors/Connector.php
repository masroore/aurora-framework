<?php

namespace Aurora\Database\Connectors;

use PDO;

abstract class Connector
{
    /**
     * The PDO connection options.
     *
     * @var array
     */
    protected $options = [
        \PDO::ATTR_CASE => \PDO::CASE_LOWER,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * Establish a PDO database connection.
     *
     * @param array $config
     *
     * @return \PDO
     */
    abstract public function connect($config);

    /**
     * Get the PDO connection options for the configuration.
     *
     * Developer specified options will override the default connection options.
     *
     * @param array $config
     *
     * @return array
     */
    protected function options($config)
    {
        $options = array_get($config, 'options', []);

        return array_diff_key($this->options, $options) + $options;
    }
}
