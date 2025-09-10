<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidRolePermission implements Rule
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
        return in_array($value, array_merge(config('quickfund.available_permissions'), config('quickfund.default_permissions')));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute contains an invalid role permission.';
    }
}
