<?php

namespace App\Services\Phone;

class Nigeria
{
    
    /**
     * Convert a phone number to the Nigerian international equivalent
     */
    public function convert($phone)
    {
        // For the case if the number starts with +
        if (str_starts_with($phone, '+234')) {
            // Check if the number after the +234 is 0 then we remove it
            if (substr($phone, 4, 1) == '0') {
                return '234'.substr($phone, 5);
            }

            return substr($phone, 1);
        }

        // If the number starts with just 234
        if (str_starts_with($phone, '234')) {
            // Check if the number after the 234 is 0 then we remove it
            if (substr($phone, 3, 1) == '0') {
                return '234'.substr($phone, 4);
            }

            return $phone;
        }

        // If the number starts with just 0, then remove 0 and append the 234 to it
        if (str_starts_with($phone, '0')) {
            return '234'.substr($phone, 1);
        }

        // Fallback to something weird that doesn't satisfy our case
        return $phone;
    }

}
