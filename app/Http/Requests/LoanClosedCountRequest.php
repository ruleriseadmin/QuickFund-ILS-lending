<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoanClosedCountRequest extends FormRequest
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
            'from_date' => ['nullable', 'required_with:to_date', 'date', 'before_or_equal:to_date'],
            'to_date' => ['nullable', 'required_with:from_date', 'date', 'after_or_equal:from_date'],
        ];
    }
}
