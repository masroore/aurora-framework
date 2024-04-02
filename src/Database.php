<?php

namespace Aurora;

use Aurora\Database\Connection;
use Aurora\Database\Expression;
use PDO;

class Database
{
    /**
     * The established database connections.
     *
     * @var array
     */
    public static $connections = [];

    /**
     * The third-party driver registrar.
     *
     * @var array
     */
    public static $registrar = [];

    /**
     * Magic Method for calling methods on the default database connection.
     *
     * <code>
     *        // Get the driver name for the default database connection
     *        $driver = DB::driver();
     *
     *        // Execute a fluent query on the default database connection
     *        $users = DB::table('users')->get();
     * </code>
     */
    public static function __callStatic($method, $parameters)
    {
        return \call_user_func_array([static::connection(), $method], $parameters);
    }

    /**
     * Begin a fluent query against a table.
     *
     * @param string $table
     * @param string $connection
     *
     * @return Database\Query
     */
    public static function table($table, $connection = null)
    {
        return static::connection($connection)->table($table);
    }

    /**
     * Get a database connection.
     *
     * If no database name is specified, the default connection will be returned.
     *
     * <code>
     *        // Get the default database connection for the application
     *        $connection = DB::connection();
     *
     *        // Get a specific connection by passing the connection name
     *        $connection = DB::connection('mysql');
     * </code>
     *
     * @param string $connection
     *
     * @return Connection
     */
    public static function connection($connection = null)
    {
        if (null === $connection) {
            $connection = Config::get('database.default');
        }

        if (!isset(static::$connections[$connection])) {
            $config = Config::get("database.connections.{$connection}");

            if (null === $config) {
                throw new \Exception("Database connection is not defined for [$connection].");
            }

            static::$connections[$connection] = new Connection(static::connect($config), $config);
        }

        return static::$connections[$connection];
    }

    /**
     * Create a new database expression instance.
     *
     * Database expressions are used to inject raw SQL into a fluent query.
     *
     * @param string $value
     *
     * @return Expression
     */
    public static function raw($value)
    {
        return new Expression($value);
    }

    /**
     * Escape a string for usage in a query.
     *
     * This uses the correct quoting mechanism for the default database connection.
     *
     * @param string $value
     *
     * @return string
     */
    public static function escape($value)
    {
        return static::connection()->quote($value);
    }

    /**
     * Get the profiling data for all queries.
     *
     * @return array
     */
    public static function profile()
    {
        return Connection::$queries;
    }

    /**
     * Get the last query that was executed.
     *
     * Returns false if no queries have been executed yet.
     *
     * @return string
     */
    public static function last_query()
    {
        return end(Connection::$queries);
    }

    /**
     * Register a database connector and grammars.
     *
     * @param string   $name
     * @param \Closure $query
     * @param \Closure $schema
     */
    public static function extend($name, \Closure $connector, $query = null, $schema = null): void
    {
        if (null === $query) {
            $query = '\Aurora\Database\Query\Grammars\Grammar';
        }

        static::$registrar[$name] = compact('connector', 'query', 'schema');
    }

    /**
     * Get a PDO database connection for a given database configuration.
     *
     * @param array $config
     *
     * @return \PDO
     */
    protected static function connect($config)
    {
        return static::connector($config['driver'])->connect($config);
    }

    /**
     * Create a new database connector instance.
     *
     * @param string $driver
     *
     * @return Database\Connectors\Connector
     */
    protected static function connector($driver)
    {
        if (isset(static::$registrar[$driver])) {
            $resolver = static::$registrar[$driver]['connector'];

            return $resolver();
        }

        switch ($driver) {
            case 'sqlite':
                return new Database\Connectors\SQLiteConnector();

            case 'mysql':
                return new Database\Connectors\MySQLConnector();

            case 'pgsql':
                return new Database\Connectors\PostgresConnector();

            case 'sqlsrv':
                return new Database\Connectors\SQLServerConnector();

            default:
                throw new \Exception("Database driver [$driver] is not supported.");
        }
    }
}
