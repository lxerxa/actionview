<?php
namespace App\Acl;

use App\Acl\Eloquent\Role;
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
        return Role::whereRaw([ 'permissions' => $permission, 'user_ids' => $user_id, 'project_key' => $project_key ])->get() ? true : false;
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
        $permissions_list = Role::whereRaw([ 'user_ids' => $user_id, 'project_key' => $project_key ])->get(['permissions']);

        $all_permissions = [];
        foreach ($permissions_list as $val)
        {
            $all_permissions += $val['permissions'] ?: [];
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
