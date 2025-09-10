<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionCaseRequest extends FormRequest
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
            'remark' => ['required'],
            'promised_to_pay_at' => ['nullable', 'date', 'after_or_equal:today'],
            'already_paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'comment' => ['nullable']
        ];
    }
}
