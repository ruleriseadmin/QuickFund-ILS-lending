<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Booleanable;

class StoreSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'minimum_loan_amount' => ['required', 'integer', 'min:1', 'lt:maximum_loan_amount'],
            'maximum_loan_amount' => ['required', 'integer', 'min:1',  'gt:minimum_loan_amount'],
            'loan_tenures' => ['required', 'array'],
            'loan_tenures.*' => ['required', 'integer', 'distinct', 'gte:7'],
            'percentage_increase_for_loyal_customers' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'loan_interest' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'default_interest' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'days_to_attach_late_payment_fees' => ['required', 'integer', 'min:1'],
            'use_credit_score_check' => ['required', new Booleanable],
            'use_crc_check' => ['required', new Booleanable],
            'use_first_central_check' => ['required', new Booleanable],
            'minimum_credit_score' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'days_to_make_crc_check' => ['required', 'integer', 'min:0'],
            'days_to_make_first_central_check' => ['required', 'integer', 'min:0'],
            'total_amount_credited_per_day' => ['required', 'integer', 'min:0'],
            'maximum_amount_for_first_timers' => ['required', 'integer', 'min:1'],
            'should_give_loans' => ['required', new Booleanable],
            'emails_to_report' => ['required', 'array'],
            'emails_to_report.*' => ['required', 'email', 'distinct'],
            'use_crc_credit_score_check' => ['required', new Booleanable],
            'use_first_central_credit_score_check' => ['required', new Booleanable],
            'minimum_credit_bureau_credit_score' => ['required', 'integer', 'between:'.config('quickfund.minimum_approved_credit_score').','.config('quickfund.maximum_approved_credit_score')],
            'maximum_outstanding_loans_to_qualify' => ['required', 'integer', 'min:0'],
            'bucket_0_to_9' => ['required', 'integer', 'min:0', 'lte:bucket_10_to_19'],
            'bucket_10_to_19' => ['required', 'integer', 'min:0', 'lte:bucket_20_to_29', 'gte:bucket_0_to_9'],
            'bucket_20_to_29' => ['required', 'integer', 'min:0', 'lte:bucket_30_to_39', 'gte:bucket_10_to_19'],
            'bucket_30_to_39' => ['required', 'integer', 'min:0', 'lte:bucket_40_to_49', 'gte:bucket_20_to_29'],
            'bucket_40_to_49' => ['required', 'integer', 'min:0', 'lte:bucket_50_to_59', 'gte:bucket_30_to_39'],
            'bucket_50_to_59' => ['required', 'integer', 'min:0', 'lte:bucket_60_to_69', 'gte:bucket_40_to_49'],
            'bucket_60_to_69' => ['required', 'integer', 'min:0', 'lte:bucket_70_to_79', 'gte:bucket_50_to_59'],
            'bucket_70_to_79' => ['required', 'integer', 'min:0', 'lte:bucket_80_to_89', 'gte:bucket_60_to_69'],
            'bucket_80_to_89' => ['required', 'integer', 'min:0', 'lte:bucket_90_to_100', 'gte:bucket_70_to_79'],
            'bucket_90_to_100' => ['required', 'integer', 'min:0', 'gte:bucket_80_to_89'],
            'days_to_stop_penalty_from_accruing' => ['required', 'integer', 'min:0'],
            'minimum_days_for_demotion' => ['required', 'integer', 'lt:maximum_days_for_demotion'],
            'maximum_days_for_demotion' => ['required', 'integer', 'gt:minimum_days_for_demotion'],
            'days_to_blacklist_customer' => ['required', 'integer', 'min:0']
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'loan_tenures.*' => 'loan tenure',
            'emails_to_report.*' => 'email to report'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $loanTenures = $this->input('loan_tenures');
        $useCreditScoreCheck = $this->input('use_credit_score_check');
        $useCrcCheck = $this->input('use_crc_check');
        $useFirstCentralCheck = $this->input('use_first_central_check');
        $shouldGiveLoans = $this->input('should_give_loans');
        $useCrcCreditScoreCheck = $this->input('use_crc_credit_score_check');
        $useFirstCentralCreditScoreCheck = $this->input('use_first_central_credit_score_check');

        if (is_array($loanTenures)) {
            $this->merge([
                'loan_tenures' => array_map('intval', $loanTenures)
            ]);
        }

        if (is_string($useCreditScoreCheck)) {
            $this->merge([
                'use_credit_score_check' => strtolower($useCreditScoreCheck)
            ]);
        }

        if (is_string($useCrcCheck)) {
            $this->merge([
                'use_crc_check' => strtolower($useCrcCheck)
            ]);
        }

        if (is_string($useFirstCentralCheck)) {
            $this->merge([
                'use_first_central_check' => strtolower($useFirstCentralCheck)
            ]);
        }

        if (is_string($shouldGiveLoans)) {
            $this->merge([
                'should_give_loans' => strtolower($shouldGiveLoans)
            ]);
        }

        if (is_string($useCrcCreditScoreCheck)) {
            $this->merge([
                'use_crc_credit_score_check' => strtolower($useCrcCreditScoreCheck)
            ]);
        }

        if (is_string($useFirstCentralCreditScoreCheck)) {
            $this->merge([
                'use_first_central_credit_score_check' => strtolower($useFirstCentralCreditScoreCheck)
            ]);
        }
    }
}
