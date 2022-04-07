<?php

namespace App\Http\Middleware;

use App\System\Eloquent\SysSetting;

use App\ActiveDirectory\Eloquent\Directory;
use App\ActiveDirectory\LDAP;

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
        // Basic Authorization
        $Authorization = $request->header('Authorization');
        $token = $request->input('token');
        if (($Authorization && strpos($Authorization, 'Basic ') === 0) || ($token && (preg_match("/file\/[0-9a-z]+(\/thumbnail)?$/i", $request->path()) || preg_match("/document\/[0-9a-z]+(\/download)?$/i", $request->path()) || preg_match("/getavatar$/i", $request->path()))))
        {
            if ($Authorization && strpos($Authorization, 'Basic ') === 0)
            {
                $token = base64_decode(substr($Authorization, 6));
            }
            else 
            {
                $token = base64_decode($token);
            }

            $sections = explode(':', $token);
            if (count($sections) < 2) 
            {
                return Response()->json([ 'ecode' => -10001, 'data' => '' ]);
            }
            else
            {
                $email = $sections[0];
                array_shift($sections);
                $password = implode('', $sections);
                $user = Sentinel::authenticate([ 'email' => $email, 'password' => $password ]);
                if (!$user)
                {
                    $configs = [];
                    $directories = Directory::where('type', 'OpenLDAP')
                        ->where('invalid_flag', '<>', 1)
                        ->get();
                    foreach ($directories as $d)
                    {
                        $configs[$d->id] = $d->configs ?: [];
                    }

                    if ($configs)
                    {
                        $user = LDAP::attempt($configs, $email, $password);
                    }
                }

                if (!$user) 
                {
                    return Response()->json([ 'ecode' => -10000, 'data' => '' ]);
                }

                $request->merge([ 'currentUser' => $user ]);
            }
        }
        else
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
            $request->merge([ 'currentUser' => $user ]);
        }
        return $next($request);
    }
}
