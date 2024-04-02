<?php

namespace Aurora\Database\Schema\Grammars;

use Aurora\Database\Schema\Table;
use Aurora\Fluent;

class MySQL extends Grammar
{
    /**
     * The keyword identifier for the database system.
     *
     * @var string
     */
    public $wrapper = '`%s`';

    /**
     * Generate the SQL statements for a table creation command.
     *
     * @return array
     */
    public function create(Table $table, Fluent $command)
    {
        $columns = implode(', ', $this->columns($table));

        // First we will generate the base table creation statement. Other than auto
        // incrementing keys, no indexes will be created during the first creation
        // of the table as they're added in separate commands.
        $sql = 'CREATE TABLE ' . $this->wrap($table) . ' (' . $columns . ')';

        if (null !== $table->engine) {
            $sql .= ' ENGINE = ' . $table->engine;
        }

        if (null !== $table->charset) {
            $sql .= ' CHARACTER SET ' . $table->charset;

            if (null !== $table->collation) {
                $sql .= ' COLLATE ' . $table->collation;
            }
        }

        return $sql;
    }

    /**
     * Generate the SQL statements for a table modification command.
     *
     * @return array
     */
    public function add(Table $table, Fluent $command)
    {
        $columns = $this->columns($table);

        // Once we have the array of column definitions, we need to add "add" to the
        // front of each definition, then we'll concatenate the definitions
        // using commas like normal and generate the SQL.
        $columns = implode(', ', array_map(static fn ($column) => 'ADD ' . $column, $columns));

        return 'ALTER TABLE ' . $this->wrap($table) . ' ' . $columns;
    }

    /**
     * Generate the SQL statement for creating a primary key.
     *
     * @return string
     */
    public function primary(Table $table, Fluent $command)
    {
        return $this->key($table, $command->name(null), 'PRIMARY KEY');
    }

    /**
     * Generate the SQL statement for creating a unique index.
     *
     * @return string
     */
    public function unique(Table $table, Fluent $command)
    {
        return $this->key($table, $command, 'UNIQUE');
    }

    /**
     * Generate the SQL statement for creating a full-text index.
     *
     * @return string
     */
    public function fulltext(Table $table, Fluent $command)
    {
        return $this->key($table, $command, 'FULLTEXT');
    }

    /**
     * Generate the SQL statement for creating a regular index.
     *
     * @return string
     */
    public function index(Table $table, Fluent $command)
    {
        return $this->key($table, $command, 'INDEX');
    }

    /**
     * Generate the SQL statement for a rename table command.
     *
     * @return string
     */
    public function rename(Table $table, Fluent $command)
    {
        return 'RENAME TABLE ' . $this->wrap($table) . ' TO ' . $this->wrap($command->name);
    }

    /**
     * Generate the SQL statement for a drop column command.
     *
     * @return string
     */
    public function drop_column(Table $table, Fluent $command)
    {
        $columns = array_map([$this, 'wrap'], $command->columns);

        // Once we the array of column names, we need to add "drop" to the front
        // of each column, then we'll concatenate the columns using commas and
        // generate the alter statement SQL.
        $columns = implode(', ', array_map(static fn ($column) => 'DROP ' . $column, $columns));

        return 'ALTER TABLE ' . $this->wrap($table) . ' ' . $columns;
    }

    /**
     * Generate the SQL statement for a drop primary key command.
     *
     * @return string
     */
    public function drop_primary(Table $table, Fluent $command)
    {
        return 'ALTER TABLE ' . $this->wrap($table) . ' DROP PRIMARY KEY';
    }

    /**
     * Generate the SQL statement for a drop unique key command.
     *
     * @return string
     */
    public function drop_unique(Table $table, Fluent $command)
    {
        return $this->drop_key($table, $command);
    }

    /**
     * Generate the SQL statement for a drop full-text key command.
     *
     * @return string
     */
    public function drop_fulltext(Table $table, Fluent $command)
    {
        return $this->drop_key($table, $command);
    }

    /**
     * Generate the SQL statement for a drop unique key command.
     *
     * @return string
     */
    public function drop_index(Table $table, Fluent $command)
    {
        return $this->drop_key($table, $command);
    }

    /**
     * Drop a foreign key constraint from the table.
     *
     * @return string
     */
    public function drop_foreign(Table $table, Fluent $command)
    {
        return 'ALTER TABLE ' . $this->wrap($table) . ' DROP FOREIGN KEY ' . $command->name;
    }

    /**
     * Create the individual column definitions for the table.
     *
     * @return array
     */
    protected function columns(Table $table)
    {
        $columns = [];

        foreach ($table->columns as $column) {
            // Each of the data type's have their own definition creation method,
            // which is responsible for creating the SQL for the type. This lets
            // us to keep the syntax easy and fluent, while translating the
            // types to the correct types.
            $sql = $this->wrap($column) . ' ' . $this->type($column);

            $elements = ['unsigned', 'nullable', 'defaults', 'incrementer'];

            foreach ($elements as $element) {
                $sql .= $this->$element($table, $column);
            }

            $columns[] = $sql;
        }

        return $columns;
    }

    /**
     * Generate the SQL statement for creating a new index.
     *
     * @param string $type
     *
     * @return string
     */
    protected function key(Table $table, Fluent $command, $type)
    {
        $keys = $this->columnize($command->columns);

        $name = $command->name;

        return 'ALTER TABLE ' . $this->wrap($table) . " ADD {$type} {$name}({$keys})";
    }

    /**
     * Generate the SQL statement for a drop key command.
     *
     * @return string
     */
    protected function drop_key(Table $table, Fluent $command)
    {
        return 'ALTER TABLE ' . $this->wrap($table) . " DROP INDEX {$command->name}";
    }

    /**
     * Get the SQL syntax for indicating if a column is unsigned.
     *
     * @return string
     */
    protected function unsigned(Table $table, Fluent $column)
    {
        if ('integer' === $column->type && ($column->unsigned || $column->increment)) {
            return ' UNSIGNED';
        }
    }

    /**
     * Get the SQL syntax for indicating if a column is nullable.
     *
     * @return string
     */
    protected function nullable(Table $table, Fluent $column)
    {
        return ($column->nullable) ? ' NULL' : ' NOT NULL';
    }

    /**
     * Get the SQL syntax for specifying a default value on a column.
     *
     * @return string
     */
    protected function defaults(Table $table, Fluent $column)
    {
        if (null !== $column->default) {
            return " DEFAULT '" . $this->default_value($column->default) . "'";
        }
    }

    /**
     * Get the SQL syntax for defining an auto-incrementing column.
     *
     * @return string
     */
    protected function incrementer(Table $table, Fluent $column)
    {
        if ('integer' === $column->type && $column->increment) {
            return ' AUTO_INCREMENT PRIMARY KEY';
        }
    }

    /**
     * Generate the data-type definition for a string.
     *
     * @return string
     */
    protected function type_string(Fluent $column)
    {
        return 'VARCHAR(' . $column->length . ')';
    }

    /**
     * Generate the data-type definition for an integer.
     *
     * @return string
     */
    protected function type_integer(Fluent $column)
    {
        return 'INT';
    }

    /**
     * Generate the data-type definition for an integer.
     *
     * @return string
     */
    protected function type_float(Fluent $column)
    {
        return 'FLOAT';
    }

    /**
     * Generate the data-type definition for a decimal.
     *
     * @return string
     */
    protected function type_decimal(Fluent $column)
    {
        return "DECIMAL({$column->precision}, {$column->scale})";
    }

    /**
     * Generate the data-type definition for a boolean.
     *
     * @return string
     */
    protected function type_boolean(Fluent $column)
    {
        return 'TINYINT(1)';
    }

    /**
     * Generate the data-type definition for a date.
     *
     * @return string
     */
    protected function type_date(Fluent $column)
    {
        return $column->useCurrent ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'DATETIME';
    }

    /**
     * Generate the data-type definition for a timestamp.
     *
     * @return string
     */
    protected function type_timestamp(Fluent $column)
    {
        return $column->useCurrent ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP';
    }

    /**
     * Generate the data-type definition for a text column.
     *
     * @return string
     */
    protected function type_text(Fluent $column)
    {
        return 'TEXT';
    }

    /**
     * Generate the data-type definition for a blob.
     *
     * @return string
     */
    protected function type_blob(Fluent $column)
    {
        return 'BLOB';
    }
}
