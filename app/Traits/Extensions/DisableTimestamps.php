<?php

namespace App\Traits\Extensions;

trait DisableTimestamps
{

    /**
     * Disable timestamps
     */
    public function withoutTimestamps(callable $callback)
    {
        $this->timestamps = false;

        return $this;
    }
}