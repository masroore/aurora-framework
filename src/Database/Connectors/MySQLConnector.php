<?php

namespace Aurora\Database\Connectors;

use PDO;

class MySQLConnector extends Connector
{
    /**
     * Establish a PDO database connection.
     *
     * @param array $config
     *
     * @return \PDO
     */
    public function connect($config)
    {
        $dsn = $this->getDsn($config);

        $username = array_get($config, 'username');

        $password = array_get($config, 'password');

        $connection = new \PDO($dsn, $username, $password, $this->options($config));

        // Next we will set the "names" and "collation" on the clients connections so
        // a correct character set will be used by this client. The collation also
        // is set on the server but needs to be set here on this client objects.
        $collation = $config['collation'];

        $charset = $config['charset'];

        $names = "SET NAMES '$charset'" . (null !== $collation ? " COLLATE '$collation'" : '');

        $connection->prepare($names)->execute();

        // If the "strict" option has been configured for the connection we'll enable
        // strict mode on all of these tables. This enforces some extra rules when
        // using the MySQLConnector database system and is a quicker way to enforce them.
        if (isset($config['strict']) && $config['strict']) {
            $connection->prepare("SET SESSION SQL_MODE='STRICT_ALL_TABLES'")->execute();
        }

        return $connection;
    }

    /**
     * Create a DSN string from a configuration. Chooses socket or host/port based on
     * the 'unix_socket' config value.
     *
     * @return string
     */
    protected function getDsn(array $config)
    {
        return $this->configHasSocket($config) ? $this->getSocketDsn($config) : $this->getHostDsn($config);
    }

    /**
     * Determine if the given configuration array has a UNIX socket value.
     *
     * @return bool
     */
    protected function configHasSocket(array $config)
    {
        return isset($config['unix_socket']) && !empty($config['unix_socket']);
    }

    /**
     * Get the DSN string for a socket configuration.
     *
     * @return string
     */
    protected function getSocketDsn(array $config)
    {
        extract($config, \EXTR_OVERWRITE);

        return "mysql:unix_socket={$unix_socket};dbname={$database}";
    }

    /**
     * Get the DSN string for a host / port configuration.
     *
     * @return string
     */
    protected function getHostDsn(array $config)
    {
        extract($config, \EXTR_OVERWRITE);

        return isset($config['port'])
            ? "mysql:host={$host};port={$port};dbname={$database}"
            : "mysql:host={$host};dbname={$database}";
    }
}
