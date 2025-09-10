<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Traits\Response\{
    Application as ApplicationResponse,
    Interswitch as InterswitchResponse
};
use App\Exceptions\Interswitch\AuthenticationException as InterswitchAuthenticationException;
use App\Exceptions\ModelNotFoundException as ApplicationModelNotFoundException;

class Handler extends ExceptionHandler
{
    use ApplicationResponse, InterswitchResponse;

    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // For unknown model instances
        if ($e instanceof ModelNotFoundException) {
            throw new ApplicationModelNotFoundException($e->getModel());
        }

        // For interswitch basic authentication error
        if ($e instanceof UnauthorizedHttpException) {
            throw new InterswitchAuthenticationException($e->getHeaders());
        }

        // For missing ability
        if ($e instanceof MissingAbilityException) {
            throw new ForbiddenException;
        }

        return parent::render($request, $e);
    }
}
