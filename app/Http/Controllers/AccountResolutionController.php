<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AccountResolutionRequest;
use App\Services\Interswitch as InterswitchService;

class AccountResolutionController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(AccountResolutionRequest $request)
    {
        $data = $request->validated();

        $accountResolutionDetails = app()->make(InterswitchService::class)->accountResolution(
            $data['account_number'],
            $data['bank_code'],
            true
        );

        return $this->sendSuccess(__('app.request_successful'), 200, $accountResolutionDetails);
    }
}
