<?php

namespace Aurora\Database\Query\Grammars;

class MySQLGrammar extends Grammar
{
    /**
     * The keyword identifier for the database system.
     *
     * @var string
     */
    protected $wrapper = '`%s`';
}
