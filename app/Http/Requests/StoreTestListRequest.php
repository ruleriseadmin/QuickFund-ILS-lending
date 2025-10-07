<?php

namespace App\Http\Requests;

use App\Rules\Phone;
use App\Rules\CustomerExists;
use App\Rules\NotBlacklisted;
use App\Rules\NotWhitelisted;
use Illuminate\Foundation\Http\FormRequest;

class StoreTestListRequest extends FormRequest
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
            'phone_number' => ['required', 'bail', new Phone, new CustomerExists, new NotWhitelisted, new NotBlacklisted]
        ];
    }
}
