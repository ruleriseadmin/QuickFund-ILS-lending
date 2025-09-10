<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Traits\External\Validator;
use App\Rules\Phone;

class GetOffersRequest extends FormRequest
{
    use Validator;

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
            'customerId' => ['required', new Phone],
            'amount' => ['nullable', 'integer'],
            'channelCode' => ['nullable'],
            'providerCode' => ['nullable']
        ];
    }

}
