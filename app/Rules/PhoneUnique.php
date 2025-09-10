<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Services\Phone\Nigeria as NigerianPhone;
use App\Models\User;

class PhoneUnique implements Rule
{
    /**
     * The ID of the user to exclude
     */
    private $userId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($userId = null)
    {
        $this->userId = $userId;
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
        if (isset($this->userId)) {
            return User::where('phone', app()->make(NigerianPhone::class)->convert($value))
                    ->where('id', '!=', $this->userId)
                    ->doesntExist();
        }

        return User::where('phone', app()->make(NigerianPhone::class)->convert($value))
                ->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute has already been taken.';
    }
}
