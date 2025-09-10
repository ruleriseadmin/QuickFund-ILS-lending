<?php

namespace App\Exceptions;

use Exception;
use App\Traits\Response\Application;

class CustomException extends Exception
{
    use Application;

    /**
     * The status code
     */
    private $statusCode;

    /**
     * Create an instance
     */
    public function __construct($message, $statusCode)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

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
        return $this->sendErrorMessage($this->getMessage(), $this->statusCode);
    }
}
