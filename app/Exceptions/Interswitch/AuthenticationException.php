<?php

namespace App\Exceptions\Interswitch;

use Exception;
use App\Traits\Response\Interswitch;

class AuthenticationException extends Exception
{
    use Interswitch;

    /**
     * The headers
     */
    private $headers;

    /**
     * Create an instance
     */
    public function __construct($headers)
    {
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
        if ($request->accepts(['text/xml', 'application/xml']) ||
            $request->hasHeader('SOAPAction')) {
            return $this->sendInterswitchXmlErrorMessage('104', __('app.unauthenticated'), 401, $this->headers);
        }

        return $this->sendInterswitchCustomMessage('104', __('app.unauthenticated'), 401, $this->headers);
    }
}
