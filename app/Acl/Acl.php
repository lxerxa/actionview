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
        return Roleactor::where('project_key', $project_key)->orwhere('project_key', '$_sys_$')->get();
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
        return Roleactor::whereRaw([ 'user_ids' => $user_id, 'project_key' => $project_key ])->get(['role_id']);
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
        $role_ids = [];
        $roles = Role::whereRaw([ 'permissions' => $permission, 'project_key' => [ '$in' => [ $project_key, '$_sys_$' ] ] ])->get();
        foreach ($roles as $role)
        {
            $role_ids[] = $role->id;
        }

        $user_ids = [];
        $role_actors = Roleactor::whereRaw([ 'project_key' => $project_key, 'role_id' => [ '$in' => $role_ids ] ])->get();
        foreach ($role_actors as $actor)
        {
            $user_ids = array_merge($user_ids, $actor->user_ids);
        }
        return array_values(array_unique($user_ids));
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
        $permissions = self::getPermissions($user_id, $project_key);
        return in_array($permission, $permissions);
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
            $all_permissions = array_merge($all_permissions, $role['permissions'] ?: [ 'watch_project' ]);
        }
        return array_values(array_unique($all_permissions));
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
