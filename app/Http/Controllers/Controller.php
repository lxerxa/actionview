<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use App\Project\Eloquent\Project;
use App\Project\Eloquent\Watch;
use App\Project\Provider;
use App\Acl\Acl;
use Sentinel;
use DB;

use MongoDB\BSON\ObjectID; 

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        $this->user = Sentinel::getUser(); 
    }

    public function arrange($data)
    {
        if (!is_array($data))
        {
            return $data;
        }

        if (array_key_exists('_id', $data))
        {
            $data['_id'] = $data['_id'] instanceof ObjectID ? $data['_id']->__toString() : $data['_id'];
        }

        foreach ($data as $k => $val)
        {
            $data[$k] = $this->arrange($val);
        }

        return $data;
    }

    /**
     * if the permission is allowed in the project.
     *
     * string $project_key
     * string $permission
     * @return bool
     */
    public function isPermissionAllowed($project_key, $permission, $user_id='')
    {
        $uid = isset($user_id) && $user_id ? $user_id : $this->user->id;

        $isAllowed = Acl::isAllowed($uid, $permission, $project_key);
        if (!$isAllowed && in_array($permission, [ 'view_project', 'manage_project' ]))
        {
            if ($this->user->email === 'admin@action.view')
            {
                return true;
            }

            $project = Project::where([ 'key' => $project_key ])->first();
            if ($project && isset($project->principal) && isset($project->principal['id']) && $uid === $project->principal['id'])
            {
                return true;
            }
        }
        return $isAllowed;
    }

    /**
     * check if the field is used by issue.
     *
     * @return true 
     */
    public function isFieldUsedByIssue($project_key, $field_key, $field, $ext_info='')
    {
        if ($field['project_key'] !== $project_key)
        {
             return true;
        }

        if ($project_key === '$_sys_$')
        {
            switch($field_key)
            {
                case 'type':
                    return false;
                case 'state':
                case 'priority':
                case 'resolution':
                    $projects = Project::all();
                    foreach($projects as $project)
                    {
                        $isUsed = DB::collection('issue_' . $project->key)
                                      ->where($field_key, isset($field['key']) ? $field['key'] : $field['_id'])
                                      ->where('del_flg', '<>', 1)
                                      ->exists();
                        if ($isUsed)
                        {
                            return true;
                        }
                    }
                    return false;
                default:
                    return true;
            }
        }
        else
        {
            switch($field_key)
            {
                case 'type':
                case 'state':
                case 'priority':
                case 'resolution':
                case 'epic':
                    return DB::collection('issue_' . $project_key)
                        ->where($field_key, $field['_id'])
                        ->where('del_flg', '<>', 1)
                        ->exists();
                case 'module':
                    return DB::collection('issue_' . $project_key)
                        ->where($field_key, $field['_id'])
                        ->where('del_flg', '<>', 1)
                        ->exists();
                case 'version':
                    if (!$ext_info)
                    {
                        return false;
                    }

                    $vid = $field['_id'];
                    return DB::collection('issue_' . $project_key)
                        ->where(function ($query) use ($vid, $ext_info) {
                            foreach ($ext_info as $key => $vf) 
                            {
                                $query->orWhere($vf['key'], $vid);
                            }
                        })
                        ->where('del_flg', '<>', 1)
                        ->exists();
                default:
                    return true;
            }
        }
    }

    public function getIssueQueryWhere($project_key, $query)
    {
        $special_fields = [
            [ 'key' => 'no', 'type' => 'Number' ],
            [ 'key' => 'type', 'type' => 'Select' ],
            [ 'key' => 'state', 'type' => 'Select' ],
            [ 'key' => 'assignee', 'type' => 'SingleUser' ],
            [ 'key' => 'reporter', 'type' => 'SingleUser' ],
            [ 'key' => 'resolver', 'type' => 'SingleUser' ],
            [ 'key' => 'closer', 'type' => 'SingleUser' ],

            [ 'key' => 'created_at', 'type' => 'Duration' ],
            [ 'key' => 'updated_at', 'type' => 'Duration' ],
            [ 'key' => 'resolved_at', 'type' => 'Duration' ],
            [ 'key' => 'closed_at', 'type' => 'Duration' ],

            [ 'key' => 'sprints', 'type' => 'Select' ],
        ];

        $fields = Provider::getFieldList($project_key, ['key', 'name', 'type']);
        // merge into the all valid fields in the project
        $all_fields = array_merge($fields ? $fields->toArray() : [], $special_fields);
        // convert into key-type array
        $key_type_fields = [];
        foreach ($all_fields as $key => $val) 
        {
            $key_type_fields[$val['key']] = $val['type'];
        }
        // get the query where value
        $where = array_only($query, array_column($all_fields, 'key'));

        $and = [];
        foreach ($where as $key => $val)
        {
            if ($key === 'no')
            {
                $and[] = [ 'no' => intval($val) ];
            }
            else if ($key === 'title')
            {
                if (is_numeric($val) && strpos($val, '.') === false)
                {
                    $and[] = [ '$or' => [ [ 'no' => $val + 0 ], [ 'title'  => [ '$regex' => $val ] ] ] ];
                }
                else if (strpos($val, ',') !== false)
                {
                    $nos = explode(',', $val);
                    $new_nos = [];
                    foreach ($nos as $no)
                    {
                        if ($no && is_numeric($no))
                        {
                            $new_nos[] = $no + 0;
                        }
                    }
                    $and[] = [ '$or' => [ [ 'no' => [ '$in' => $new_nos ] ], [ 'title'  => [ '$regex' => $val ] ] ] ];
                }
                else
                {
                    $and[] = [ 'title' => [ '$regex' => $val ] ];
                }
            }
            else if ($key === 'sprints')
            {
                $and[] = [ 'sprints' => $val + 0 ];
            }
            else if ($key_type_fields[$key] === 'SingleUser')
            {
                $users = explode(',', $val);
                if (in_array('me', $users))
                {
                    array_push($users, $this->user->id);
                }
                $and[] = [ $key . '.' . 'id' => [ '$in' => $users ] ];
            }
            else if ($key_type_fields[$key] === 'MultiUser')
            {
                $or = [];
                $vals = explode(',', $val);
                foreach ($vals as $v)
                {
                    $or[] = [ $key . '_ids' => $v ];
                }
                $and[] = [ '$or' => $or ];
            }
            else if (in_array($key_type_fields[$key], [ 'Select', 'SingleVersion', 'RadioGroup' ]))
            {
                $and[] = [ $key => [ '$in' => explode(',', $val) ] ];
            }
            else if (in_array($key_type_fields[$key], [ 'MultiSelect', 'MultiVersion', 'CheckboxGroup' ]))
            {
                $or = [];
                $vals = explode(',', $val);
                foreach ($vals as $v)
                {
                    $or[] = [ $key => $v ];
                }
                $and[] = [ '$or' => $or ];
            }
            else if (in_array($key_type_fields[$key], [ 'Duration', 'DatePicker', 'DateTimePicker' ]))
            {
                if (strpos($val, '~') !== false)
                {
                    $sections = explode('~', $val);
                    if ($sections[0])
                    {
                        $and[] = [ $key => [ '$gte' => strtotime($sections[0]) ] ];
                    }
                    if ($sections[1])
                    {
                        $and[] = [ $key => [ '$lte' => strtotime($sections[1] . ' 23:59:59') ] ];
                    }
                }
                else
                {
                    $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
                    $unit = substr($val, -1);
                    if (in_array($unit, [ 'w', 'm', 'y' ]))
                    {
                        $direct = substr($val, 0, 1);
                        $val = abs(substr($val, 0, -1));
                        if ($direct === '-')
                        {
                            $and[] = [ $key => [ '$lt' => strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))) ] ];
                        }
                        else
                        {
                            $and[] = [ $key => [ '$gte' => strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))) ] ];
                        }
                    }
                }
            }
            else if (in_array($key_type_fields[$key], [ 'Text', 'TextArea', 'Url' ]))
            {
                $and[] = [ $key => [ '$regex' => $val ] ];
            }
            else if ($key_type_fields[$key] === 'Number')
            {
                if (strpos($val, '~') !== false)
                {
                    $sections = explode('~', $val);
                    if ($sections[0])
                    {
                        $and[] = [ $key => [ '$gte' => $sections[0] + 0 ] ];
                    }
                    if ($sections[1])
                    {
                        $and[] = [ $key => [ '$lte' => $sections[1] + 0 ] ];
                    }
                }
            }
            else if ($key_type_fields[$key] === 'TimeTracking')
            {
                if (strpos($val, '~') !== false)
                {
                    $sections = explode('~', $val);
                    if ($sections[0])
                    {
                        $and[] = [ $key . '_m' => [ '$gte' => $this->ttHandleInM($sections[0]) ] ];
                    }
                    if ($sections[1])
                    {
                        $and[] = [ $key . '_m' => [ '$lte' => $this->ttHandleInM($sections[1]) ] ];
                    }
                }
            }
        }

        if (isset($query['watcher']) && $query['watcher'])
        {
            $watcher = $query['watcher'] === 'me' ? $this->user->id : $query['watcher'];

            $watched_issues = Watch::where('project_key', $project_key)
                ->where('user.id', $watcher)
                ->get()
                ->toArray();
            $watched_issue_ids = array_column($watched_issues, 'issue_id');

            $watchedIds = [];
            foreach ($watched_issue_ids as $id)
            {
                $watchedIds[] = new ObjectID($id);
            }
            $and[] = [ '_id' => [ '$in' => $watchedIds ] ];
        }

        $and[] = [ 'del_flg' => [ '$ne' => 1 ] ];
        return [ '$and' => $and ];
    }
}
