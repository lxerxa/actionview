<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use App\Project\Eloquent\Project;
use App\Project\Eloquent\Watch;
use App\System\Eloquent\SysSetting;
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
     * check the timetracking.
     *
     * @return bool
     */
    public function ttCheck($ttString)
    {
        $ttString = strtolower(trim($ttString));
        $ttValues = explode(' ', $ttString);
        foreach ($ttValues as $ttValue)
        {
            if (!$ttValue)
            {
                continue;
            }

            $lastChr = substr($ttValue, -1);
            if ($lastChr !== 'w' && $lastChr !== 'd' && $lastChr !== 'h' && $lastChr !== 'm')
            {
                return false;
            }

            $ttNum = substr($ttValue, 0, -1);
            if ($ttNum && !is_numeric($ttNum))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * handle the timetracking in the minute.
     *
     * @return string
     */
    public function ttHandleInM($ttString)
    {
        if (!$ttString)
        {
            return '';
        }

        $W2D = 5;
        $D2H = 8;
        $setting = SysSetting::first();
        if ($setting && isset($setting->properties))
        {
            if (isset($setting->properties['week2day']))
            {
                $W2D = $setting->properties['week2day'];
            }
            if (isset($setting->properties['day2hour']))
            {
                $D2H = $setting->properties['day2hour'];
            }
        }

        $W2M = $W2D * $D2H * 60;
        $D2M = $D2H * 60;
        $H2M = 60;

        $tt_in_min = 0;

        $ttString = strtolower(trim($ttString));
        $ttValues = explode(' ', $ttString);
        foreach ($ttValues as $ttValue)
        {
            if (!$ttValue)
            {
                continue;
            }

            $lastChr = substr($ttValue, -1);
            $ttNum   = substr($ttValue, 0, -1) === '' ? 1 : substr($ttValue, 0, -1);

            if ($lastChr == 'w')
            {
                $tt_in_min += $ttNum * $W2M;
            }
            else if ($lastChr == 'd')
            {
                $tt_in_min += $ttNum * $D2M;
            }
            else if ($lastChr == 'h')
            {
                $tt_in_min += $ttNum * $H2M;
            }
            else if ($lastChr == 'm')
            {
                $tt_in_min += $ttNum;
            }
        }

        return $tt_in_min;
    } 

    /**
     * handle the timetracking.
     *
     * @return string
     */
    public function ttHandle($ttString)
    {
        if (!$ttString)
        {
            return '';
        }
        
        $W2D = 5;
        $D2H = 8;
        $setting = SysSetting::first();
        if ($setting && isset($setting->properties))
        {
            if (isset($setting->properties['week2day']))
            {
                $W2D = $setting->properties['week2day'];
            }
            if (isset($setting->properties['day2hour']))
            {
                $D2H = $setting->properties['day2hour'];
            }
        }

        $W2M = $W2D * $D2H * 60;
        $D2M = $D2H * 60;
        $H2M = 60;

        $tt_in_min = 0;

        $ttString = strtolower(trim($ttString));
        $ttValues = explode(' ', $ttString);
        foreach ($ttValues as $ttValue)
        {
            if (!$ttValue)
            {
                continue;
            }

            $lastChr = substr($ttValue, -1);
            $ttNum   = substr($ttValue, 0, -1) === '' ? 1 : abs(substr($ttValue, 0, -1));

            if ($lastChr == 'w')
            {
                $tt_in_min += $ttNum * $W2M;
            }
            else if ($lastChr == 'd')
            {
                $tt_in_min += $ttNum * $D2M;
            }
            else if ($lastChr == 'h')
            {
                $tt_in_min += $ttNum * $H2M;
            }
            else if ($lastChr == 'm')
            {
                $tt_in_min += $ttNum;
            }
        }

        $newTT = [];
        $new_remain_min = ceil($tt_in_min);
        if ($new_remain_min >= 0)
        {
            $new_weeknum = floor($tt_in_min / $W2M);
            if ($new_weeknum > 0)
            {
                $newTT[] = $new_weeknum . 'w';
            }
        }

        $new_remain_min = $tt_in_min % $W2M;
        if ($new_remain_min >= 0)
        {
            $new_daynum = floor($new_remain_min / $D2M);
            if ($new_daynum > 0)
            {
                $newTT[] = $new_daynum . 'd';
            }
        }

        $new_remain_min = $new_remain_min % $D2M;
        if ($new_remain_min >= 0)
        {
            $new_hournum = floor($new_remain_min / $H2M);
            if ($new_hournum > 0)
            {
                $newTT[] = $new_hournum . 'h';
            }
        }

        $new_remain_min = $new_remain_min % $H2M;
        if ($new_remain_min > 0)
        {
            $newTT[] = $new_remain_min . 'm';
        }

        if (!$newTT)
        {
            $newTT[] = '0m';
        }

        return (substr($ttString, 0, 1) == '-' ? '-' : '') . implode(' ', $newTT);
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
                        ->where($field_key, 'like', '%' . $field['_id'] . '%')
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
                                if ($vf['type'] === 'SingleVersion') 
                                {
                                    $query->orWhere($vf['key'], $vid);
                                } 
                                else 
                                {
                                    $query->orWhere($vf['key'], 'like',  "%$vid%");
                                }
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
        $where = array_only($query, [ 
            'type', 
            'assignee', 
            'reporter', 
            'resolver', 
            'closer', 
            'state', 
            'resolution', 
            'priority', 
            'resolve_version', 
            'epic' ]);

        $and = []; 
        foreach ($where as $key => $val)
        {
            if (in_array($key, [ 'assignee', 'reporter', 'resolver', 'closer' ]))
            {
                $users = explode(',', $val);
                if (in_array('me', $users))
                {
                    array_push($users, $this->user->id);
                }

                $and[] = [ $key . '.' . 'id' => [ '$in' => $users ] ];
            }
            else
            {
                $and[] = [ $key => [ '$in' => explode(',', $val) ] ];
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

        $sprint = isset($query['sprint']) ? $query['sprint'] : '';
        if ($sprint)
        {
            $and[] = [ 'sprints' => intval($sprint) ];
        }

        $no = isset($query['no']) ? $query['no'] : '';
        if ($no)
        {
            $and[] = [ 'no' => intval($no) ];
        }

        $title = isset($query['title']) ? $query['title'] : '';
        if ($title)
        {
            if (is_numeric($title) && strpos($title, '.') === false)
            {
                $and[] = [ '$or' => [ [ 'no' => $title + 0 ], [ 'title'  => [ '$regex' => $title ] ] ] ];
            }
            else if (strpos($title, ',') !== false)
            {
                $nos = explode(',', $title);
                $new_nos = [];
                foreach ($nos as $no)
                {
                    if ($no && is_numeric($no))
                    {
                        $new_nos[] = $no + 0;
                    }
                }
                $and[] = [ '$or' => [ [ 'no' => [ '$in' => $new_nos ] ], [ 'title'  => [ '$regex' => $title ] ] ] ];
            }
            else
            {
                $and[] = [ 'title' => [ '$regex' => $title ] ];
            }
        }

        $effect_versions = isset($query['effect_versions']) ? $query['effect_versions'] : '';
        if ($effect_versions)
        {
            $or = [];
            $versions = explode(',', $effect_versions);
            foreach ($version as $ver)
            {
                $or[] = [ 'effect_versions' => [ '$regex' => $ver ] ];
            }
            $and[] = [ '$or' => $or ];
        }

        $module = isset($query['module']) ? $query['module'] : '';
        if ($module)
        {
            $or = [];
            $modules = explode(',', $module);
            foreach ($modules as $m)
            {
                $or[] = [ 'module' => [ '$regex' => $m ] ];
            }
            $and[] = [ '$or' => $or ];
        }

        $labels = isset($query['labels']) ? $query['labels'] : '';
        if (isset($labels) && $labels)
        {
            $or = [];
            $labels = explode(',', $labels);
            foreach ($labels as $label)
            {
                $or[] = [ 'labels' => $label ];
            }
            $and[] = [ '$or' => $or ];
        }

        //$query = DB::collection('issue_' . $project_key);
        //if ($and)
        //{
        //    $query = $query->whereRaw([ '$and' => $and ]);
        //}

        $timeConds = [ 'created_at', 'updated_at', 'resolved_at', 'closed_at', 'expect_complete_time' ];
        foreach ($timeConds as $cond)
        {
            if (!isset($query[$cond]) || !$query[$cond])
            {
                continue;
            }

            if (strpos($query[$cond], '~') !== false)
            {
                $sections = explode('~', $query[$cond]);
                if ($sections[0])
                {
                    $and[] = [ $cond => [ '$gte' => strtotime($sections[0]) ] ];
                }
                if ($sections[1])
                {
                    $and[] = [ $cond => [ '$lte' => strtotime($sections[1] . ' 23:59:59') ] ];
                }
            }
            else
            {
                $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
                $unit = substr($query[$cond], -1);
                if (in_array($unit, [ 'w', 'm', 'y' ]))
                {
                    $direct = substr($query[$cond], 0, 1);
                    $val = abs(substr($query[$cond], 0, -1));
                    if ($direct === '-')
                    {
                        $and[] = [ $cond => [ '$lt' => strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))) ] ];
                    }
                    else
                    {
                        $and[] = [ $cond => [ '$gte' => strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))) ] ];
                    }
                }
            }
        }

        $and[] = [ 'del_flg' => [ '$ne' => 1 ] ];

        return [ '$and' => $and ];
    }
}
