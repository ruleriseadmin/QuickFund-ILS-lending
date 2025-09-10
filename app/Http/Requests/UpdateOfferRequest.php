<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\{ValidOfferAmount, ValidOfferTenure};

class UpdateOfferRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1', new ValidOfferAmount],
            'tenure' => ['required', 'bail', 'integer', 'min:1', new ValidOfferTenure],
            'cycles' => ['required', 'integer', 'min:1'],
            'fees' => ['nullable', 'array'],
            'fees.*' => ['nullable', 'distinct', 'exists:fees,id'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:today']
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
            'fees.*' => 'fee'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'exists' => 'The :attribute does not exist'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $fees = $this->input('fees');

        if (is_array($fees)) {
            $this->merge([
                'fees' => array_map('intval', $fees)
            ]);
        }
    }
}
