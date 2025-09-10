<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Setting;

class ValidOfferAmount implements Rule
{

    /**
     * The minimum loan amount
     */
    private $minimumLoanAmount;

    /**
     * The maximum loan amount
     */
    private $maximumLoanAmount;

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

        $this->minimumLoanAmount = $setting?->minimum_loan_amount ?? config('quickfund.minimum_loan_amount');
        $this->maximumLoanAmount = $setting?->maximum_loan_amount ?? config('quickfund.maximum_loan_amount');

        return ($this->minimumLoanAmount <= $value) && ($value <= $this->maximumLoanAmount);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must not be less that '.config('quickfund.currency_representation').number_format($this->minimumLoanAmount / 100, 2).' and greater than '.config('quickfund.currency_representation').number_format($this->maximumLoanAmount / 100, 2).'.';
    }
}
