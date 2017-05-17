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
        else if ($permission === 'project_principal')
        {
            if (! $this->principalCheck($request) && ! $this->globalCheck('sys_admin'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else if ($permission === 'join_project')
        {
            if ($request->isMethod('get')) 
            {
                if (! $this->projectCheck($request, 'join_project'))
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
    public function principalCheck($request)
    {
        if (! $pid = $request->id)
        {
            return false;
        }

        $project = Project::find($pid);
        if (!$project || !isset($project->principal) || !isset($project->principal['id']))
        {
            return false;
        }

        $user = Sentinel::getUser();
        return $user->id === $project->principal['id'];
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
        else 
        {
            return Acl::isAllowed($user->id, $permission, $project_key);
        }
    }
}
