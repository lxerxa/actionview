<?php
namespace App\Acl;

use App\Acl\Eloquent\Role;
use App\Acl\Eloquent\Action;

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
     * get user list who has the action allow in the project.
     *
     * @var string action
     * @var string project_key
     * @return array
     */
    public static function getUserIdsByAction($action, $project_key)
    {
        $roles = Role::whereRaw([ 'actions' => $action, 'project_key' => $project_key ])->get();
        $user_ids = [];
        foreach ($roles as $role)
        {
            $user_ids += $role->user_ids;
        }
        return array_unique($user_ids);
    }

    /**
     * check if user has action allow.
     *
     * @var string $user_id
     * @var string $action
     * @var string $project_key
     * @return boolean
     */
    public static function isAllowed($user_id, $action, $project_key)
    {
        return Role::whereRaw([ 'actions' => $action, 'user_ids' => $user_id, 'project_key' => $project_key ])->get() ? true : false;
    }

    /**
     * get user's all actions in the project.
     *
     * @var string $user_id
     * @var string $project_key
     * @return array
     */
    public static function getActions($user_id, $project_key)
    {
        $actions_list = Role::whereRaw([ 'user_ids' => $user_id, 'project_key' => $project_key ])->get(['actions']);

        $all_actions = [];
        foreach ($actions_list as $val)
        {
            $all_actions += $val['actions'] ?: [];
        }
        return array_unique($all_actions);
    }

    /**
     * get action list.
     *
     * @return array
     */
    public static function getAllActions()
    {
        return Action::all();
    }
}
