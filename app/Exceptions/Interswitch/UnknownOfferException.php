<?php

namespace App\Exceptions\Interswitch;

use Exception;
use App\Traits\Response\Interswitch;

class UnknownOfferException extends Exception
{
    use Interswitch;

    /**
     * Create an instance
     */
    public function __construct()
    {
        parent::__construct(__('interswitch.unknown_offer'));
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
        return $this->sendInterswitchUnknownOfferMessage($this->getMessage());
    }
}
