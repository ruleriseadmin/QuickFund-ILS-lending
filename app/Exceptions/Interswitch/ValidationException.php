<?php

namespace App\Exceptions\Interswitch;

use Illuminate\Validation\ValidationException as BaseValidationException;
use App\Traits\Response\Interswitch;

class ValidationException extends BaseValidationException
{
    use Interswitch;

    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        //
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        return $this->sendInterswitchValidationErrorMessage($this->getMessage());
    }
}
