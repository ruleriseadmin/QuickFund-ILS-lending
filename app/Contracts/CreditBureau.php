<?php

namespace App\Contracts;

interface CreditBureau
{
    /**
     * Check if the credit bureau check passes
     */
    public function passesCheck($customer, $setting);
}