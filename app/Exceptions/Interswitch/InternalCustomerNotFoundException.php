<?php

namespace App\Exceptions\Interswitch;

use Exception;
use App\Traits\Response\Interswitch;

class InternalCustomerNotFoundException extends Exception
{
    use Interswitch;

    /**
     * Create an instance
     */
    public function __construct()
    {
        parent::__construct(__('interswitch.customer_not_found'));
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
        return $this->sendInterswitchInternalCustomerNotFoundMessage($this->getMessage());
    }
}
