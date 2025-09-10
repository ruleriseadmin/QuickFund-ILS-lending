<?php

namespace App\Traits\Response;

trait Application
{

    /**
     * Format the error message
     */
    protected function sendErrorMessage($message, $status = 400)
    {
        return response(compact('message'), $status);
    }

    /**
     * The format for success responses
     */
    protected function sendSuccess($message, $status = 200, $data = null)
    {
        if (func_num_args() < 3) {
            return response(compact('message'), $status);
        }

        return response(compact('message', 'data'), $status);
    }

}
