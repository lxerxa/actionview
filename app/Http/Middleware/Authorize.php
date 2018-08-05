<?php

namespace App\Http\Middleware;

use App\System\Eloquent\SysSetting;

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
        $setting = SysSetting::first();
        if (!($setting && isset($setting->properties) && isset($setting->properties['enable_login_protection']) && $setting->properties['enable_login_protection'] === 1))
        {
            Sentinel::removeCheckpoint('throttle');
        }

        if (! $user = Sentinel::check())
        {
            return Response()->json([ 'ecode' => -10001, 'data' => '' ]);
        }
        return $next($request);
    }
}
