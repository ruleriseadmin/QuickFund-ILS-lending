<?php

namespace App\Services\Calculation;

use Illuminate\Support\Carbon;

class Date
{
    /**
     * Get the day difference
     */
    public function dayDifference($today, $dueDate, $timezone)
    {
        $timestampDifference = Carbon::parse(Carbon::parse($today)->format('Y-m-d'))->getTimestamp() - Carbon::parse($dueDate)->timezone($timezone)->getTimestamp();

        return (int) ($timestampDifference / 86400);
    }
}