<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Exceptions\Interswitch\{CustomException, XmlException};

class InterswitchMiddleware
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
        if ($request->user()->id !== User::INTERSWITCH_ID) {
            if ($request->accepts(['text/xml', 'application/xml']) ||
                $request->hasHeader('SOAPAction')) {
                throw new XmlException('104', __('app.unauthorized'), 403);
            }

            throw new CustomException('104', __('app.unauthorized'), 403);
        }

        return $next($request);
    }
}
