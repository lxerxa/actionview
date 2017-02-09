<?php

namespace App\Workflow;
use App\Project\Provider;
use App\Acl\Acl;
use Sentinel;
use DB;

class Func 
{

    public static $issue_properties = [];

    /**
     * check if user is the type.
     *
     * @param  array $param  
     * @return boolean 
     */
    public static function isSome($param)
    {
        $issue_id = $param['issue_id'];
        $project_key = $param['project_key'];
        $caller = $param['caller'];

        if ($param['someParam'] == 'assignee')
        {
            $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
            if ($issue && isset($issue['assignee']) && isset($issue['assignee']['id']) && $issue['assignee']['id'] === $caller)
            {
                return true;
            }
        }
        else if ($param['someParam'] == 'reporter')
        {
            $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
            if ($issue && isset($issue['reporter']) && isset($issue['reporter']['id']) && $issue['reporter']['id'] === $caller)
            {
                return true;
            }
        }
        else if ($param['someParam'] == 'principal')
        {
            $principal = Provider::getProjectPrincipal($project_key) ?: [];
            return $principal && $principal['id'] === $caller;
        }

        return false;
    }

    /**
     * check if user is the user.
     *
     * @param  array $param
     * @return boolean
     */
    public static function isTheUser($param)
    {
        return $param['caller'] === $param['userParam'];
    }

    public static function checkSubTasksState($param)
    {
    }

    /**
     * check if user has permission allow.
     *
     * @param  array $param
     * @return boolean
     */
    public static function hasPermission($param)
    {
        $project_key = $param['project_key'];
        $caller = $param['caller'];
        $permission = $param['permissionParam'];

        return Acl::isAllowed($caller, $permission, $project_key);
    }

    /**
     * check if user belongs to the role.
     *
     * @param  array $param
     * @return boolean
     */
    public static function belongsToRole($param)
    {
        $project_key = $param['project_key'];
        $caller = $param['caller'];

        $roles = Acl::getRolesByUid($caller, $project_key);
        foreach ($roles as $role)
        {
            if ($role->id === $param['roleParam'])
            {
                return true;
            }
        }
        return false;
    }

    /**
     * set resolution value to issue_properties.
     *
     * @param  array $param
     * @return void 
     */
    public static function setResolution($param)
    {
        if (isset($param['resolutionParam']) && $param['resolutionParam'])
        {
            array_push(self::$issue_properties, [ 'resolution' => $param['resolutionParam'] ]);
        }
    }

    /**
     * set state value to issue_properties.
     *
     * @param  array $param
     * @return void
     */
    public static function setState($param)
    {
        if (isset($param['state']) && $param['state'])
        {
            array_push(self::$issue_properties, [ 'state' => $param['state'] ]);
        }
    }

    /**
     * set assignee value to issue_properties.
     *
     * @param  array $param
     * @return void
     */
    public static function assignIssueToUser($param)
    {
       $user_info = Sentinel::findById($param['assignedUserParam']);
       if ($user_info)
       {
           array_push(self::$issue_properties, [ 'assignee' => [ 'id' => $user_info->id, 'name' => $user_info->first_name ] ]);
       }
    }

    /**
     * set assignee value to issue_properties.
     *
     * @param  array $param
     * @return void
     */
    public static function assignIssue($param)
    {
        $project_key = $param['project_key'];
        $issue_id = $param['issue_id'];
        $caller = $param['caller'];

        if ($param['someParam'] == 'me')
        {
            $user_info = Sentinel::findById($caller);
            if ($user_info)
            {
                array_push(self::$issue_properties, [ 'assignee' => [ 'id' => $user_info->id, 'name' => $user_info->first_name ] ]);
            }
        }
        else if ($param['someParam'] == 'reporter')
        {
            $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
            if ($issue && isset($issue['reporter']))
            {
                array_push(self::$issue_properties, [ 'assignee' => $issue['reporter'] ]);
            }
        }
        else if ($param['someParam'] == 'principal')
        {
            $principal = Provider::getProjectPrincipal($project_key) ?: [];
            if ($principal)
            {
                array_push(self::$issue_properties, [ 'assignee' => $principal ]);
            }
        }
    }

    /**
     * update issue.
     *
     * @param  array $param
     * @return void
     */
    public static function updIssue($param)
    {
        $issue_id = $param['issue_id'];
        $project_key = $param['project_key'];

        if (count(self::$issue_properties) > 0)
        {
            $updValues = [];
            $user_info = Sentinel::findById($caller);
            $updValues['modifier'] = [ 'id' => $user_info->id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
            $updValues['updated_at'] = time();
            
            // update issue
            DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update(self::$issue_properties + $updValues);
            // snap to history
            Provider::snap2His($project_key, $issue_id, null, array_keys(self::$issue_properties));
        }
    }
}
