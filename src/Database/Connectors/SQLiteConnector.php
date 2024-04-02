<?php

namespace Aurora\Database\Connectors;

use PDO;

class SQLiteConnector extends Connector
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
        $options = $this->options($config);

        // SQLiteConnector provides supported for "in-memory" databases, which exist only for
        // lifetime of the request. Any given in-memory database may only have one
        // PDO connection open to it at a time. These are mainly for tests.
        if (':memory:' === $config['database']) {
            return new \PDO('sqlite::memory:', null, null, $options);
        }

        $path = realpath($config['database']);

        // Here we'll verify that the SQLiteConnector database exists before going any further
        // as the developer probably wants to know if the database exists and this
        // SQLiteConnector driver will not throw any exception if it does not by default.
        if (false === $path) {
            throw new \InvalidArgumentException('Database does not exist.');
        }

        return new \PDO('sqlite:' . $path, null, null, $options);
    }
}
