<?php

namespace App\Http\Middleware;

use Closure;
use Sentinel;

class CheckPersistenceCode 
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
        if (! Sentinel::check())
        {
            return Response()->json(['ecode' => -10001, 'data' => 'aaa']);
        }
        return $next($request);
    }
}
