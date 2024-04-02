<?php

namespace Aurora\Database\Connectors;

use PDO;

class PostgresConnector extends Connector
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
    ];

    /**
     * Establish a PDO database connection.
     *
     * @param array $config
     *
     * @return \PDO
     */
    public function connect($config)
    {
        // First we'll create the basic DSN and connection instance connecting to the
        // using the configuration option specified by the developer. We will also
        // set the default character set on the connections to UTF-8 by default.
        $dsn = $this->getDsn($config);

        $username = array_get($config, 'username');

        $password = array_get($config, 'password');

        $connection = new \PDO($dsn, $username, $password, $this->options($config));

        // If a character set has been specified, we'll execute a query against
        // the database to set the correct character set. By default, this is
        // set to UTF-8 which should be fine for most scenarios.
        $connection->prepare("SET NAMES '{$config['charset']}'")->execute();

        // If a schema has been specified, we'll execute a query against
        // the database to set the search path.
        if (isset($config['schema'])) {
            $connection->prepare("SET search_path TO {$config['schema']}")->execute();
        }

        return $connection;
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @return string
     */
    protected function getDsn(array $config)
    {
        // First we will create the basic DSN setup as well as the port if it is in
        // in the configuration options. This will give us the basic DSN we will
        // need to establish the PDO connections and return them back for use.
        extract($config, \EXTR_OVERWRITE);

        $host = isset($host) ? "host={$host};" : '';

        $dsn = "pgsql:{$host}dbname={$database}";

        // If a port was specified, we will add it to this PostgresConnector DSN connections
        // format. Once we have done that we are ready to return this connection
        // string back out for usage, as this has been fully constructed here.
        if (isset($config['port'])) {
            $dsn .= ";port={$port}";
        }

        if (isset($config['sslmode'])) {
            $dsn .= ";sslmode={$sslmode}";
        }

        return $dsn;
    }
}
