<?php

namespace App\Traits\Extensions;

trait LoanManager
{

    /**
     * Check if the customer defaulted in the loan payment
     */
    public function defaulted()
    {
        return $this->defaults > 0;
    }

}