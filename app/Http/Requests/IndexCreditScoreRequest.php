<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCreditScoreRequest extends FormRequest
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
            'score_from' => ['nullable', 'required_with:score_to', 'integer', 'lte:score_to'],
            'score_to' => ['nullable', 'required_with:score_from', 'integer', 'gte:score_from'],
        ];
    }
}
