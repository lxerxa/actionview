<?php

namespace App\Http\Middleware;

use Closure;
use Sentinel;

class Authorize 
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->session()->get('user');
        if (!isset($user) || !$user)
        {
            return Response()->json([ 'ecode' => -10001, 'data' => '' ]);
        }
        return $next($request);
    }
}
