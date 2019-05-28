<?php

namespace App\Http\Middleware;

use App\Project\Eloquent\Project;
use App\Acl\Acl;

use Closure;
use Sentinel;

class Privilege
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permission)
    {
        // global permission check
        if ($permission === 'sys_admin')
        {
            if (! $this->globalCheck('sys_admin'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else if ($permission === 'view_project')
        {
            if ($request->isMethod('get')) 
            {
                if (! $this->projectCheck($request, 'view_project'))
                {
                    return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                }
            }
        }
        // project permission check
        else
        {
            if (! $this->projectCheck($request, $permission))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        return $next($request);
    }

    /**
     * Handle an incoming request for global.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function globalCheck($permission)
    {
        $user = Sentinel::getUser();
        return $user->hasAccess($permission);

        //if ($permission == 'manage_user' && $request->isMethod('put'))
        //{
        //    if ($user->id == $request->id)
        //    {
        //        return true;
        //    }
        //}
        //else if ($permission == 'manage_project' && $request->isMethod('put'))
        //{
        //    $principal = Project::find($request->id)->first()->principal;
        //    if ($principal == $user->id)
        //    {
        //        return true;
        //    }
        //}
    }

    /**
     * Handle an incoming request for project.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function projectCheck($request, $permission)
    {
        $user = Sentinel::getUser();
        $project_key = $request->project_key;

        if ($project_key === '$_sys_$')
        {
            return $user->hasAccess([ 'sys_admin' ]);
        }

        $isAllowed = Acl::isAllowed($user->id, $permission, $project_key);
        if (!$isAllowed)
        {
            if ($permission === 'manage_project' || $permission === 'view_project')
            {
                if ($user->email === 'admin@action.view')
                {
                     return true;
                }

                $project = Project::where('key', $project_key)->first();
                if ($project && isset($project->principal) && isset($project->principal['id']) && $project->principal['id'] === $user->id)
                {
                    return true;
                }
            }
        }
        return $isAllowed;
    }
}
