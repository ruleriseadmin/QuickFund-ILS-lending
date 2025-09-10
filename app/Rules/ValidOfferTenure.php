<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Setting;

class ValidOfferTenure implements Rule
{
    
    /**
     * The allowed tenures of the application
     */
    private $allowedLoanTenures;

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
        $setting = Setting::find(Setting::MAIN_ID);
        
        $this->allowedLoanTenures = $setting?->loan_tenures ?? config('quickfund.loan_tenures');

        return in_array((int) $value, $this->allowedLoanTenures);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be one of '.implode(' or ', collect($this->allowedLoanTenures)->map(fn($tenure) => "{$tenure}")->all()).' days.';
    }
}
