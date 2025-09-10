<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\LoanOffer;

class UpdateLoanStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                LoanOffer::PENDING,
                LoanOffer::ACCEPTED,
                LoanOffer::DECLINED,
                LoanOffer::FAILED,
                LoanOffer::OVERDUE,
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::NONE
            ])]
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $status = $this->input('status');

        if (is_string($status)) {
            $this->merge([
                'status' => strtoupper($status)
            ]);
        }
    }
}
