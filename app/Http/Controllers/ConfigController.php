<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;
use App\Project\Provider;
use App\Acl\Eloquent\RolePermissions;

class ConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $new_types = [];
        $types = Provider::getTypeList($project_key); 
        foreach ($types as $type)
        {
            if (isset($type->disabled) && $type->disabled)
            {
                continue;
            }
            $type->screen = $type->screen;
            $type->workflow = $type->workflow;
            $new_types[] = $type;
        }

        $priorities = Provider::getPriorityList($project_key);
        $roles = Provider::getRoleList($project_key);
        foreach($roles as $role)
        {
            $role->permissions = $this->getPermissions($project_key, $role->id);
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'types' => $new_types, 'roles' => $roles, 'priorities' => $priorities ] ]);
    }

    /**
     * get permissions by project_key and roleid.
     *
     * @param  string $project_key
     * @param  string $role_id
     * @return array
     */
    public function getPermissions($project_key, $role_id)
    {
        $rp = RolePermissions::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        if (!$rp && $project_key !== '$_sys_$')
        {
            $rp = RolePermissions::where([ 'project_key' => '$_sys_$', 'role_id' => $role_id ])->first();
        }
        return $rp && isset($rp->permissions) ? $rp->permissions : [];
    }
}
