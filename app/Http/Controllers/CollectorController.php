<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{User, Role};

class CollectorController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $collectors = User::whereHas('role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%'))
                        ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $collectors);
    }
}
