<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Str;
use App\Traits\Response\Application;

class ModelNotFoundException extends Exception
{
    use Application;

    /**
     * The model
     */
    private $model;

    /**
     * Create an instance
     */
    public function __construct($model)
    {
        $this->model = $model;
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
        // Get the table belonging to the model
        $table = (new $this->model)->getTable();

        return $this->sendErrorMessage(Str::of($table)->singular()->replace('_', ' ')->ucfirst().' not found', 404);
    }
}
