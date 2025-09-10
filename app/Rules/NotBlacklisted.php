<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Blacklist;
use App\Services\Phone\Nigeria as NigerianPhone;

class NotBlacklisted implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($value))->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute has been blacklisted.';
    }
}
