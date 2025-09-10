<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Role;

class SearchCollectionCaseRequest extends FormRequest
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
            'collector_id' => ['nullable', 'exists:users,id'],
            'remark' => ['nullable'],
            'assigned_date' => ['nullable', 'date'],
            'dpd_from' => ['nullable', 'required_with:dpd_to', 'integer', 'min:0', 'lte:dpd_to'],
            'dpd_to' => ['nullable', 'required_with:dpd_from', 'integer', 'min:0', 'gte:dpd_from'],
            'ptp_from' => ['nullable', 'required_with:ptp_to', 'date', 'before_or_equal:ptp_to'],
            'ptp_to' => ['nullable', 'required_with:ptp_from', 'date', 'after_or_equal:ptp_from']
        ];
    }
}
