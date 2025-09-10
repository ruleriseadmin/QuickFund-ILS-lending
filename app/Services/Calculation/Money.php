<?php

namespace App\Services\Calculation;

use App\Models\Fee;

class Money
{

    /**
     * The value to calculate
     */
    private $value;

    /**
     * The fee
     */
    private $fee;

    /**
     * Create an instance
     */
    public function __construct($value, $fees = null)
    {
        $this->value = $value;
        $this->fees = $fees;
    }

    /**
     * Convert value to the higher denominational value
     */
    public function toHigherDenomination()
    {
        $this->value = (int) $this->value / 100;

        return $this;
    }

    /**
     * Calculate the total amount payable
     */
    public function totalPayable($interest)
    {
        $this->value = $this->value + ($this->value * ($interest / 100));

        // Check if there was a fee
        if (isset($this->fees)) {
            $fees = Fee::find($this->fees);

            $totalFees = $fees->sum('amount');

            $this->value = $this->value + $totalFees;
        }

        return $this;
    }

    /**
     * Get the value
     */
    public function getValue()
    {
        return $this->value;
    }

}