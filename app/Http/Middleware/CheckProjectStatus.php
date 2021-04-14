<?php

namespace App\Http\Middleware; 
 
use App\Project\Eloquent\Project; 

use Closure; 

class CheckProjectStatus 
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
        $project_key = $request->project_key; 
        if (!$request->isMethod('get') && $project_key !== '$_sys_$')
        {
            $project = Project::where('key', $project_key)->first(); 
            if ($project->status != 'active')
            {
                return Response()->json(['ecode' => -14009, 'emsg' => 'the project has been archived.']); 
            }
        }
 
        return $next($request); 
    } 
}
