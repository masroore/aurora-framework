<?php

namespace Aurora\Database\Schema\Grammars;

use Aurora\Database\Schema\Table;
use Aurora\Fluent;

abstract class Grammar extends \Aurora\Database\Grammar
{
    /**
     * Generate the SQL statement for creating a foreign key.
     *
     * @return string
     */
    public function foreign(Table $table, Fluent $command)
    {
        $name = $command->name;

        // We need to wrap both of the table names in quoted identifiers to protect
        // against any possible keyword collisions, both the table on which the
        // command is being executed and the referenced table are wrapped.
        $table = $this->wrap($table);

        $on = $this->wrapTable($command->on);

        // Next we need to columnize both the command table's columns as well as
        // the columns referenced by the foreign key. We'll cast the referenced
        // columns to an array since they aren't by the fluent command.
        $foreign = $this->columnize($command->columns);

        $referenced = $this->columnize((array)$command->references);

        $sql = "ALTER TABLE $table ADD CONSTRAINT $name ";

        $sql .= "FOREIGN KEY ($foreign) REFERENCES $on ($referenced)";

        // Finally we will check for any "on delete" or "on update" options for
        // the foreign key. These control the behavior of the constraint when
        // an update or delete statement is run against the record.
        if (null !== $command->on_delete) {
            $sql .= " ON DELETE {$command->on_delete}";
        }

        if (null !== $command->on_update) {
            $sql .= " ON UPDATE {$command->on_update}";
        }

        return $sql;
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param string|Table $value
     *
     * @return string
     */
    public function wrap($value)
    {
        // This method is primarily for convenience so we can just pass a
        // column or table instance into the wrap method without sending
        // in the name each time we need to wrap one of these objects.
        if ($value instanceof Table) {
            return $this->wrapTable($value->name);
        }
        if ($value instanceof Fluent) {
            $value = $value->name;
        }

        return parent::wrap($value);
    }

    /**
     * Generate the SQL statement for a drop table command.
     *
     * @return string
     */
    public function drop(Table $table, Fluent $command)
    {
        return 'DROP TABLE ' . $this->wrap($table);
    }

    /**
     * Drop a constraint from the table.
     *
     * @return string
     */
    protected function drop_constraint(Table $table, Fluent $command)
    {
        return 'ALTER TABLE ' . $this->wrap($table) . ' DROP CONSTRAINT ' . $command->name;
    }

    /**
     * Get the appropriate data type definition for the column.
     *
     * @return string
     */
    protected function type(Fluent $column)
    {
        return $this->{'type_' . $column->type}($column);
    }

    /**
     * Format a value so that it can be used in SQL DEFAULT clauses.
     *
     * @return string
     */
    protected function default_value($value)
    {
        if (\is_bool($value)) {
            return (int)$value;
        }

        return (string)$value;
    }
}
