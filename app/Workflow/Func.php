<?php
namespace App\Workflow;

use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

use App\Project\Provider;
use App\Acl\Acl;
use Sentinel;
use DB;

class Func 
{

    public static $issue_properties = [];

    public static $snap_id = '';

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

    /**
     * check if subtask's state .
     *
     * @param  array $param
     * @return boolean
     */
    public static function checkSubTasksState($param)
    {
        $issue_id = $param['issue_id'];
        $project_key = $param['project_key'];

        $subtasks = DB::collection('issue_' . $project_key)->where('parent_id', $issue_id)->get([ 'state' ]);
        foreach ($subtasks as $subtask)
        {
            if ($subtask['state'] != $param['stateParam'])
            {
                return false;
            }
        }

        return true;
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

        $roles = Acl::getRolesByUid($project_key, $caller);
        foreach ($roles as $role)
        {
            if ($role === $param['roleParam'])
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
            self::$issue_properties[ 'resolution'] = $param['resolutionParam'];
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
            self::$issue_properties['state'] = $param['state'];
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
            self::$issue_properties['assignee'] = [ 'id' => $user_info->id, 'name' => $user_info->first_name ];
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

        if ($param['assigneeParam'] == 'me')
        {
            $user_info = Sentinel::findById($caller);
            if ($user_info)
            {
                self::$issue_properties['assignee'] = [ 'id' => $user_info->id, 'name' => $user_info->first_name ];
            }
        }
        else if ($param['assigneeParam'] == 'reporter')
        {
            $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
            if ($issue && isset($issue['reporter']))
            {
                self::$issue_properties['assignee'] = $issue['reporter'];
            }
        }
        else if ($param['assigneeParam'] == 'principal')
        {
            $principal = Provider::getProjectPrincipal($project_key) ?: [];
            if ($principal)
            {
                self::$issue_properties['assignee'] = $principal;
            }
        }
    }

    /**
     * update issue.
     *
     * @param  array $param
     * @return void
     */
    public static function addComments($param)
    {
        $issue_id = $param['issue_id'];
        $project_key = $param['project_key'];
        $caller = $param['caller'];
        $comments = isset($param['comments']) ? $param['comments'] : '';

        if (!$comments) { return; }

        $user_info = Sentinel::findById($caller);
        $creator = [ 'id' => $user_info->id, 'name' => $user_info->first_name, 'email' => $user_info->email ];

        $table = 'comments_' . $project_key;
        DB::collection($table)->insert([ 'contents' => $comments, 'atWho' => [], 'issue_id' => $issue_id, 'creator' => $creator, 'created_at' => time() ]);

        // trigger event of comments added
        Event::fire(new IssueEvent($project_key, $issue_id, $creator, [ 'event_key' => 'add_comments', 'data' => [ 'contents' => $comments, 'atWho' => [] ] ]));
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
        $caller = $param['caller'];

        if (count(self::$issue_properties) > 0)
        {
            $updValues = [];
            $user_info = Sentinel::findById($caller);
            $updValues['modifier'] = [ 'id' => $user_info->id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
            $updValues['updated_at'] = time();
            
            // update issue
            DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update(self::$issue_properties + $updValues);
            // snap to history
            $snap_id = Provider::snap2His($project_key, $issue_id, null, array_keys(self::$issue_properties));

            //if (!isset($param['eventParam']) || !$param['eventParam'])
            //{
            //    Event::fire(new IssueEvent($project_key, $issue_id, $updValues['modifier'], [ 'event_key' => 'normal', 'snap_id' => $snap_id ]));
            //}

            self::$snap_id = $snap_id;
        }
    }

    /**
     * trigger issue.event
     *
     * @param  array $param
     * @return void
     */
    public static function triggerEvent($param)
    {
        $issue_id = $param['issue_id'];
        $project_key = $param['project_key'];
        $event_key = array_get($param, 'eventParam', 'normal'); 

        $user_info = Sentinel::findById($param['caller']);
        $caller = [ 'id' => $user_info->id, 'name' => $user_info->first_name, 'email' => $user_info->email ];

        if (self::$snap_id) 
        {
            Event::fire(new IssueEvent($project_key, $issue_id, $caller, [ 'event_key' => $event_key, 'snap_id' => self::$snap_id ]));
        }

        $updValues = [];
        if ($event_key === 'resolve_issue')
        {
            $updValues['resolved_at'] = time();
            $updValues['resolver'] = $caller;

            $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
            if (isset($issue['regression_times']) && $issue['regression_times'])
            {
                $updValues['regression_times'] = $issue['regression_times'] + 1;
            }
            else
            {
                $updValues['regression_times'] = 1;
            }
            
            $logs = [];
            if (isset($issue['resolved_logs']) && $issue['resolved_logs'])
            {
                $logs = $issue['resolved_logs'];
            }
            $log = [];
            $log['user'] = $caller;
            $log['at'] = time();
            $logs[] = $log;
            $updValues['resolved_logs'] = $logs;

            $his_resolvers = [];
            foreach($logs as $v)
            {
                $his_resolvers[] = $v['user']['id'];
            }
            $updValues['his_resolvers'] = array_unique($his_resolvers);
        }
        else if ($event_key === 'close_issue')
        {
            $updValues['closed_at'] = time();
            $updValues['closer'] = $caller;
        }
        if ($updValues)
        {
            DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update($updValues);
        }
    }
}
