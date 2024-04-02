<?php

namespace Aurora\CLI\Tasks;

use Aurora\Cache as C;

class Cache extends Task
{
    /**
     * Flush Application Cache.
     */
    public function clear(): void
    {
        C::flush();
        $this->croak('Application cache cleared!');
    }
}
