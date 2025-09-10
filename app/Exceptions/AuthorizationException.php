<?php

namespace App\Exceptions;

use Exception;
use App\Traits\Response\Application;

class AuthorizationException extends Exception
{
    use Application;

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
        return $this->sendErrorMessage(__('app.unauthorized'), 403);
    }
}
