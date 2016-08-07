<?php

namespace App\Http\Middleware;

use App\Project\Eloquent\Project;
use App\Project\Eloquent\UserProject;
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
        return $next($request);  // fix me

        list($type, $permission) = explode(':', $permission);
        // global permission check
        if ($type == 'global')
        {
            if (! $this->globalCheck($request, $permission))
            {
                return Response()->json(['ecode' => -10001, 'data' => 'aaa']);
            }
        }
        // project permission check
        else if ($type == 'project')
        {
            if (! $this->projectCheck($request, $permission))
            {
                return Response()->json(['ecode' => -10001, 'data' => 'aaa']);
            }
        }
        else
        {
            return Response()->json(['ecode' => -10001, 'data' => 'aaa']);
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
    public function globalCheck($request, $permission)
    {
        $user = Sentinel::getUser();
        if ($isAllowed = $user->hasAccess([ $permission ]))
        {
            return true;
        }

        if ($permission == 'manage_user' && $request->isMethod('put'))
        {
            if ($user->id == $request->id)
            {
                return true;
            }
        }
        else if ($permission == 'manage_project' && $request->isMethod('put'))
        {
            $principal = Project::find($request->id)->first()->principal;
            if ($principal == $user->id)
            {
                return true;
            }
        }

        return false;
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

        if ($request->isMethod('get'))
        {
            return UserProject::whereRaw([ 'user_id' => $user->id, 'project_key' => $project_key ])->exists();
        }

        $isAllowed = Acl::isAllowed($user->id, $permission, $project_key);
        if ($isAllowed)
        {
            return true;
        }

        // the pricinpal of project has the admin_project permission
        if ($permission == 'admin_project')
        {
            $principal = Project::where('key', $project_key)->first()->principal;
            if ($principal == $user->id)
            {
                return true;
            }
        }

        // check if the issue's reporter
        if ($permission == 'edit_issue')
        {
        }

        // check if the comment's reporter
        if ($permission == 'edit_comments' || $permission == 'delete_comments')
        {
        }

        return false;
    }
}
