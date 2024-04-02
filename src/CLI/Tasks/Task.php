<?php

namespace Aurora\CLI\Tasks;

abstract class Task
{
    protected function croak($msg): void
    {
        echo $msg, \PHP_EOL;
    }
}
