<?php

namespace App\Exceptions\Interswitch;

use Exception;
use App\Traits\Response\Interswitch;

class CustomException extends Exception
{
    use Interswitch;

    /**
     * The response code
     */
    private $responseCode;

    /**
     * The status code
     */
    private $statusCode;

    /**
     * Create an instance
     */
    public function __construct($responseCode, $message, $statusCode)
    {
        $this->responseCode = $responseCode;
        $this->statusCode = $statusCode;

        parent::__construct($message);
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
        return $this->sendInterswitchCustomMessage($this->responseCode, $this->getMessage(), $this->statusCode);
    }
}
