<?php

namespace Aurora\Debug;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Dumper
{
    /**
     * Dump a value with elegance.
     */
    public function dump($value): void
    {
        if (class_exists('Symfony\Component\VarDumper\Dumper\CliDumper')) {
            $dumper = 'cli' === \PHP_SAPI ? new CliDumper() : new HtmlDumper();

            $dumper->dump((new VarCloner())->cloneVar($value));
        } else {
            var_dump($value);
        }
    }
}
