<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;
use App\Events\AddGroupToRoleEvent;
use App\Events\DelGroupFromRoleEvent;

use App\Project\Eloquent\Project;
use App\Project\Provider;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Group;
use App\Acl\Eloquent\Role;
use App\Acl\Eloquent\RolePermissions;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Acl;

use Cartalyst\Sentinel\Users\EloquentUser;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $roles = Provider::getRoleList($project_key)->toArray();
        foreach ($roles as $key => $role)
        {
            if ($project_key === '$_sys_$')
            {
                $actor = Roleactor::where('role_id', $role['_id'])->first();
                if ($actor && ($actor->user_ids || $actor->group_ids))
                {
                    $roles[$key]['is_used'] = true;
                }
            }
            else 
            {
                $user_groups = $this->getGroupsAndUsers($project_key, $role['_id']);
                $roles[$key]['users'] = $user_groups['users'];
                $roles[$key]['groups'] = $user_groups['groups'];

                if (isset($role['user_ids']))
                {
                    unset($roles[$key]['user_ids']);
                }
                if (isset($role['group_ids']))
                {
                    unset($roles[$key]['group_ids']);
                }
            }

            $roles[$key]['permissions'] = $this->getPermissions($project_key, $role['_id']);
        }
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
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12700);
        }

        $permissions = $request->input('permissions');
        if (isset($permissions))
        {
            $allPermissions = Acl::getAllPermissions();
            if (array_diff($permissions, $allPermissions))
            {
                throw new \UnexpectedValueException('the illegal permission.', -12701);
            }
        }

        $role = Role::create($request->all() + [ 'project_key' => $project_key ]);

        if (isset($permissions) && $role)
        {
            RolePermissions::create([ 'project_key' => $project_key, 'role_id' => $role->id, 'permissions' => $permissions ]);
            $role->permissions = $permissions;
        }

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
        return Response()->json([ 'ecode' => 0, 'data' => $role ]);
    }

    /**
     * set the role actor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setActor(Request $request, $project_key, $id)
    {
        $new_user_ids = $request->input('users');
        if (isset($new_user_ids))
        {
            $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $id ])->first();
            $old_user_ids = $actor && $actor->user_ids ? $actor->user_ids : [];

            $this->setUsers($project_key, $id, $new_user_ids ?: []);

            $add_user_ids = array_diff($new_user_ids, $old_user_ids);
            $del_user_ids = array_diff($old_user_ids, $new_user_ids);

            Event::fire(new AddUserToRoleEvent($add_user_ids, $project_key));
            Event::fire(new DelUserFromRoleEvent($del_user_ids, $project_key));
        }

        $data = Role::find($id);
        $user_groups = $this->getGroupsAndUsers($project_key, $id);
        $data->users = $user_groups['users'];
        $data->groups = $user_groups['groups'];

        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
    }

    /**
     * set the role group actor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setGroupActor(Request $request, $project_key, $id)
    {
        $new_group_ids = $request->input('groups');
        if (isset($new_group_ids))
        {
            $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $id ])->first();
            $old_group_ids = $actor && $actor->group_ids ? $actor->group_ids : [];

            $this->setGroups($project_key, $id, $new_group_ids ?: []);

            $add_group_ids = array_diff($new_group_ids, $old_group_ids);
            $del_group_ids = array_diff($old_group_ids, $new_group_ids);

            Event::fire(new AddGroupToRoleEvent($add_group_ids, $project_key));
            Event::fire(new DelGroupFromRoleEvent($del_group_ids, $project_key));
        }

        $data = Role::find($id);
        $user_groups = $this->getGroupsAndUsers($project_key, $id);
        $data->users = $user_groups['users'];
        $data->groups = $user_groups['groups'];

        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
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
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12700);
            }
        }
        $role = Role::find($id);
        if (!$role || $project_key != $role->project_key)
        {
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -12702);
        }

        $permissions = $request->input('permissions');
        if (isset($permissions))
        {
            $allPermissions = Acl::getAllPermissions();
            if (array_diff($permissions, $allPermissions))
            {
                throw new \UnexpectedValueException('the illegal permission.', -12701);
            }
            $role->permissions = $permissions;
        }
        $role->fill($request->except(['project_key']))->save();

        $data = Role::find($id);
        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
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
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -12702);
        }

        if ($project_key === '$_sys_$')
        {
            $actors = Roleactor::where('role_id', $role->id)->get();
            foreach($actors as $actor)
            {
                if ($actor->user_ids || $actor->group_ids)
                {
                    throw new \UnexpectedValueException('the role has been used in some projects.', -12703);
                }
            }
            foreach($actors as $actor)
            {
                $actor->delete();
            }
        }
        else
        {
            $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $id ])->first();
            if ($actor)
            {
                $user_ids = isset($actor->user_ids) ? $actor->user_ids : []; 
                $user_ids && Event::fire(new DelUserFromRoleEvent($user_ids, $project_key));
                $group_ids = isset($actor->group_ids) ? $actor->group_ids : [];
                $group_ids && Event::fire(new DelGroupFromRoleEvent($group_ids, $project_key));
                $actor->delete();
            }
        }
        Role::destroy($id);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * set users.
     *
     * @param  string $project_key
     * @param  array $uids
     * @return array
     */
    public function setUsers($project_key, $role_id, $uids)
    {
        $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        $actor && $actor->delete();
        
        Roleactor::create([ 'role_id' => $role_id, 'project_key' => $project_key, 'user_ids' => $uids, 'group_ids' => isset($actor->group_ids) ? $actor->group_ids : [] ]);
    }

    /**
     * set groups.
     *
     * @param  string $project_key
     * @param  array $gids
     * @return array
     */
    public function setGroups($project_key, $role_id, $gids)
    {
        $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        $actor && $actor->delete();

        Roleactor::create([ 'role_id' => $role_id, 'project_key' => $project_key, 'group_ids' => $gids, 'user_ids' => isset($actor->user_ids) ? $actor->user_ids : [] ]);
    }

    /**
     * get users and Groups by role id.
     *
     * @param  string $project_key
     * @param  string $role_id
     * @return array 
     */
    public function getGroupsAndUsers($project_key, $role_id)
    {
        $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        if (!$actor) { return [ 'users' => [], 'groups' => [] ]; }

        $new_users = [];
        if (isset($actor->user_ids) && $actor->user_ids)
        {
            $users = EloquentUser::find($actor->user_ids);
            foreach ($users as $user)
            {
                $new_users[] = [ 'id' => $user->id, 'name' => $user->first_name, 'email' => $user->email, 'nameAndEmail' => $user->first_name . '('. $user->email . ')' ];
            }
        }

        $new_groups = [];
        if (isset($actor->group_ids) && $actor->group_ids)
        {
            $new_groups = Group::find($actor->group_ids)->toArray();
        }

        return [ 'users' => $new_users, 'groups' => $new_groups ];
    }

    /**
     * set permissions 
     *
     * @param  string $project_key
     * @param  string $role_id
     * @return array
     */
    public function setPermissions(Request $request, $project_key, $id)
    {
        $role = Role::find($id);
        if (!$role || ($role->project_key != '$_sys_$' && $project_key != $role->project_key))
        {
            throw new \UnexpectedValueException('the role does not exist or is not in the project.', -12702);
        }

        $permissions = $request->input('permissions');
        if (isset($permissions))
        {
            $allPermissions = Acl::getAllPermissions();
            if (array_diff($permissions, $allPermissions))
            {
                throw new \UnexpectedValueException('the illegal permission.', -12701);
            }

            $rp = RolePermissions::where([ 'project_key' => $project_key, 'role_id' => $id ])->first();
            $rp && $rp->delete();

            RolePermissions::create([ 'project_key' => $project_key, 'role_id' => $id, 'permissions' => $permissions ]);
        }

        $role->permissions = $this->getPermissions($project_key, $id);
        return Response()->json(['ecode' => 0, 'data' => $role]);
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

    /**
     * reset the role permissions.
     *
     * @param  string  $project_key
     * @param  string  $role_id
     * @return \Illuminate\Http\Response
     */
    public function reset($project_key, $role_id)
    {
        $rp = RolePermissions::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        $rp && $rp->delete();

        $role = Role::find($role_id)->toArray();

        $rp = RolePermissions::where([ 'project_key' => '$_sys_$', 'role_id' => $role_id ])->first();
        $role['permissions'] = $rp && isset($rp->permissions) ? $rp->permissions : [];

        return Response()->json(['ecode' => 0, 'data' => $role]);
    }

    /**
     * view the application in the all projects.
     *
     * @return \Illuminate\Http\Response
     */
    public function viewUsedInProject($project_key, $id)
    {
        if ($project_key !== '$_sys_$')
        {
            return Response()->json(['ecode' => 0, 'data' => [] ]);
        }

        $res = [];
        $projects = Project::all();
        foreach($projects as $project)
        {
            $roleactor = Roleactor::where('role_id', $id)
                ->where('project_key', '<>', '$_sys_$')
                ->where('project_key', $project->key)
                ->first();

            if ($roleactor && ($roleactor->user_ids || $roleactor->group_ids))
            {
                $res[] = [ 'key' => $project->key, 'name' => $project->name, 'status' => $project->status ];
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $res ]);
    }
}
