<?php

namespace App\Http\Middleware;

use App\Sentinel\Sentinel;

use Closure;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        list($code, $user, $refreshed) = Sentinel::checkAndRefreshJWTToken($request->header('source'));
        if ($code < 0) {
            return Response()->json([ 'ecode' => -10001, 'data' => '' ]);
        } else {
            $request->merge([ 'currentUser' => $user ]);
        }

        if ($refreshed) {
            return $next($request)->withHeaders([ 'Authorization' => 'Bearer ' . $refreshed ]);
        }

        return $next($request);
    }
}
