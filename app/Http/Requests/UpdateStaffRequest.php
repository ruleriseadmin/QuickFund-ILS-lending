<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\{NoWhitespace, Phone, PhoneUnique, ValidStaffRole};

class UpdateStaffRequest extends FormRequest
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
            'first_name' => ['required', 'alpha', new NoWhitespace],
            'last_name' => ['required', 'alpha', new NoWhitespace],
            'email' => ['required','email', 'unique:users,email,'.$this->route('userId')],
            'phone' => ['required', new NoWhitespace, new Phone, new PhoneUnique($this->route('userId'))],
            'role_id' => ['required', 'integer', 'exists:roles,id', new ValidStaffRole],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'password' => ['required', 'min:5', 'confirmed'],
            'password_confirmation' => ['required']
        ];
    }
}
