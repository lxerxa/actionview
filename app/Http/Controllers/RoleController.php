<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Role;
use App\Acl\Acl;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $roles = Role::where([ 'project_key' => $project_key ])->orderBy('created_at', 'asc')->get();
        return Response()->json([ 'ecode' => 0, 'data' => $roles ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        $permissions = $request->input('permissions');
        if (isset($permissions))
        {
            $allPermissions = Acl::getAllPermissions();
            foreach ($permissions as $permission)
            {
                if (!in_array($permission, $allPermissions))
                {
                    throw new \UnexpectedValueException('the illegal permission.', -10002);
                }
            }
        }

        $role = Role::create($request->all() + [ 'project_key' => $project_key ]);
        return Response()->json([ 'ecode' => 0, 'data' => $role ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $role = Role::find($id);
        if (!$role || $project_key != $role->project_key)
        {
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -10002);
        }
        return Response()->json([ 'ecode' => 0, 'data' => $role ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }
        $role = Role::find($id);
        if (!$role || $project_key != $role->project_key)
        {
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -10002);
        }

        $permissions = $request->input('permissions');
        if (isset($permissions))
        {
            $allPermissions = Acl::getAllPermissions();
            foreach ($permissions as $permission)
            {
                if (!in_array($permission, $allPermissions))
                {
                    throw new \UnexpectedValueException('the illegal permission.', -10002);
                }
            }
            $role->permissions = $permissions;
        }

        $new_user_ids = $request->input('users');
        if (isset($new_user_ids))
        {
            $old_user_ids = $role->user_ids ?: [];
            $add_user_ids = array_diff($new_user_ids, $old_user_ids);
            $del_user_ids = array_diff($old_user_ids, $new_user_ids);
            $role->user_ids = $new_user_ids;
        }
        $role->fill($request->except(['project_key']))->save();

        isset($add_user_ids) && Event::fire(new AddUserToRoleEvent($add_user_ids, $role->project_key)); 
        isset($del_user_ids) && Event::fire(new DelUserFromRoleEvent($del_user_ids, $role->project_key)); 

        return Response()->json([ 'ecode' => 0, 'data' => Role::find($id) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $role = Role::find($id);
        if (!$role || $project_key != $role->project_key)
        {
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -10002);
        }
        $user_ids = $role->user_ids; 

        Role::destroy($id);
        $user_ids && Event::fire(new DelUserFromRoleEvent($user_ids, $project_key));
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
