<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\LoanOffer;

class SearchLoanOffersRequest extends FormRequest
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
            'status' => ['nullable', 'array'],
            'status.*' => ['nullable', 'distinct', Rule::in([
                LoanOffer::PENDING,
                LoanOffer::ACCEPTED,
                LoanOffer::DECLINED,
                LoanOffer::FAILED,
                LoanOffer::OVERDUE,
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::NONE
            ])],
            'from_date' => ['nullable', 'required_with:to_date', 'date'],
            'to_date' => ['nullable', 'required_with:from_date', 'date'],
            'due_from_date' => ['nullable', 'required_with:due_to_date', 'date'],
            'due_to_date' => ['nullable', 'required_with:due_from_date', 'date']
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

        if (is_array($status) && !empty($status)) {
            $this->merge([
                'status' => array_map(fn($oneStatus) => is_string($oneStatus) ? strtoupper($oneStatus) : $oneStatus, $status)
            ]);
        }
    }
}
