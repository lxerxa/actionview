<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Closure;

class TrimStrings 
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
        $params = $request->all();
        foreach ($params as $k => $v)
        {
            if (is_string($v)) 
            {
                $request->offsetSet($k, trim($v));
            }
        }
        return $next($request);
    }
}
