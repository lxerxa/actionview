<?php
namespace App\Acl;

use App\Acl\Eloquent\Role;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Permissions;

class Acl {

    /**
     * get role list in the project.
     *
     * @var string $project_key
     * @return collection
     */
    public static function getRoles($project_key)
    {
        return Role::where('project_key', $project_key)->get();
    }

    /**
     * get role list in the project by userid.
     *
     * @var string $project_key
     * @var string $user_id
     * @return collection
     */
    public static function getRolesByUid($project_key, $user_id)
    {
        return Role::whereRaw([ 'user_ids' => $user_id, 'project_key' => $project_key ])->get();
    }

    /**
     * get user list who has the permission allow in the project.
     *
     * @var string permission
     * @var string project_key
     * @return array
     */
    public static function getUserIdsByPermission($permission, $project_key)
    {
        $roles = Role::whereRaw([ 'permissions' => $permission, 'project_key' => $project_key ])->get();
        $user_ids = [];
        foreach ($roles as $role)
        {
            $user_ids += $role->user_ids;
        }
        return array_unique($user_ids);
    }

    /**
     * check if user has permission allow.
     *
     * @var string $user_id
     * @var string $permission
     * @var string $project_key
     * @return boolean
     */
    public static function isAllowed($user_id, $permission, $project_key)
    {
        return Role::whereRaw([ 'permissions' => $permission, 'user_ids' => $user_id, 'project_key' => $project_key ])->exists();
    }

    /**
     * get user's all permissions in the project.
     *
     * @var string $user_id
     * @var string $project_key
     * @return array
     */
    public static function getPermissions($user_id, $project_key)
    {
        $role_ids = [];
        $role_actors = Roleactor::whereRaw([ 'user_ids' => $user_id, 'project_key' => $project_key ])->get(['role_id'])->toArray();
        foreach($role_actors as $actor)
        {
            $role_ids[] =  $actor['role_id'];
        }

        $all_permissions = [];
        $roles = Role::find($role_ids)->toArray();
        foreach ($roles as $role)
        {
            $all_permissions += $role['permissions'] ?: [];
        }
        return array_unique($all_permissions);
    }

    /**
     * get permission list.
     *
     * @return array
     */
    public static function getAllPermissions()
    {
        return Permissions::all();
    }
}
