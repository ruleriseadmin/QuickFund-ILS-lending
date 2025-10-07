<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BureauCheckReportRequest extends FormRequest
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
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'bvn' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
