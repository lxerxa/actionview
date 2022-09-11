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
        list($code, $user) = Sentinel::checkJWTToken();
        if ($code < 0) {
            return Response()->json([ 'ecode' => -10001, 'data' => '' ]);
        } else {
            $request->merge([ 'currentUser' => $user ]);
        }

        $expired_at = Sentinel::getTokenExpiredAt();
        if ($expired_at - time() < 30 * 60) {
            $newToken = Sentinel::createJWTToken($user); 
            return $next($request)->withHeaders([ 'Authorization' => 'Bearer ' . $newToken ]);
        }

        return $next($request);
    }
}
