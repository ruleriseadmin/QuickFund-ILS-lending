<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActivityLog
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $request->user()
                ->activityLogs()
                ->create([
                    'route_name' => $request->route()->getName(),
                    'route_url' => $request->fullUrl(),
                    'status' => $response->getStatusCode()
                ]);

        return $response;
    }
}
