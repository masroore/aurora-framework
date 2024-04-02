<?php

namespace Aurora\Database;

class DbException extends \Exception
{
    /**
     * The inner exception.
     *
     * @var DbException
     */
    protected $inner;

    /**
     * Create a new database exception instance.
     *
     * @param string $sql
     */
    public function __construct($sql, array $bindings, \Exception $inner)
    {
        $this->inner = $inner;

        $this->setMessage($sql, $bindings);

        // Set the exception code
        $this->code = $inner->getCode();
    }

    /**
     * Get the inner exception.
     *
     * @return DbException
     */
    public function getInner()
    {
        return $this->inner;
    }

    /**
     * Set the exception message to include the SQL and bindings.
     *
     * @param string $sql
     */
    protected function setMessage($sql, array $bindings): void
    {
        $this->message = $this->inner->getMessage();

        $this->message .= "\n\nSQL: " . $sql . "\n\nBindings: " . var_export($bindings, true);
    }
}
