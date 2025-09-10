<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Transaction;

class SearchTransactionsRequest extends FormRequest
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
            'interswitch_response_code' => ['nullable'],
            'amount_from' => ['nullable', 'required_with:amount_to', 'integer', 'lte:amount_to'],
            'amount_to' => ['nullable', 'required_with:amount_from', 'integer', 'gte:amount_from'],
            'reference' => ['nullable'],
            'type' => ['nullable', 'array'],
            'type.*' => ['nullable', Rule::in([
                Transaction::DEBIT,
                Transaction::CREDIT,
                Transaction::PAYMENT,
                Transaction::REFUND,
                Transaction::NONE
            ])],
            'from_date' => ['nullable', 'required_with:to_date', 'date', 'before_or_equal:to_date'],
            'to_date' => ['nullable', 'required_with:from_date', 'date', 'after_or_equal:from_date'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $type = $this->input('type');

        if (is_array($type) && !empty($type)) {
            $this->merge([
                'type' => array_map('strtoupper', $type)
            ]);
        }
    }
}
