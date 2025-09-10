<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Booleanable implements Rule
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
        if (is_string($value)) {
            $value = strtolower($value);
        }

        return in_array($value, [
            true,
            false,
            'true',
            'false',
            0,
            1,
            '0',
            '1'
        ]);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be a boolean ("true" or "false").';
    }
}
