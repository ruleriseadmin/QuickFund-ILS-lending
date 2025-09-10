<?php

namespace App\Traits\Offers;

use Illuminate\Support\Carbon;

trait Manager
{
    /**
     * Check to see if an offer has expired
     */
    public function hasExpired()
    {
        return (isset($this->expiry_date)) && (Carbon::today() > Carbon::parse($this->expiry_date));
    }
}
