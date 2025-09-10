<?php

namespace App\Traits\External;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\ValidationException;
use App\Exceptions\Interswitch\ValidationException as InterswitchValidationException;

trait Validator
{

    /**
     * Handle a failed validation attempt.
     *
     * @param  ValidatorContract  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(ValidatorContract $validator)
    {
        throw new InterswitchValidationException($validator);
    }

}