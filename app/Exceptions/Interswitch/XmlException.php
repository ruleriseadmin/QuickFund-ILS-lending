<?php

namespace App\Exceptions\Interswitch;

use Exception;
use App\Traits\Response\Interswitch;

class XmlException extends Exception
{
    use Interswitch;

    /**
     * The status code
     */
    private $statusCode;

    /**
     * The interswitch code
     */
    private $interswitchCode;

    /**
     * The headers
     */
    private $headers;

    /**
     * Create an instance
     */
    public function __construct($interswitchCode, $message, $statusCode, $headers = [])
    {
        parent::__construct($message);
        $this->interswitchCode = $interswitchCode;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
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
        return $this->sendInterswitchXmlErrorMessage($this->interswitchCode, $this->getMessage(), $this->statusCode, $this->headers);
    }
}
