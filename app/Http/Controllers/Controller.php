<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use App\Project\Eloquent\Project;
use App\System\Eloquent\SysSetting;
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

        return implode(' ', $newTT);
    }

    /**
     * check if the field is used by issue.
     *
     * @return true 
     */
    public function isFieldUsedByIssue($project_key, $field_key, $field, $ext_info='')
    {
        if ($project_key === '$_sys_$')
        {
            switch($field_key)
            {
                case 'type':
                case 'workflow':
                case 'state':
                case 'priority':
                case 'resolution':
                    if ($field['project_key'] !== $project_key)
                    {
                        return true;
                    }
                    else
                    {
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
                    }
                default:
                    return true;
            }
        }
        else
        {
            switch($field_key)
            {
                case 'type':
                case 'workflow':
                case 'state':
                case 'priority':
                case 'resolution':
                case 'module':
                case 'epic':
                    if ($field['project_key'] !== $project_key)
                    {
                        return true;
                    }
                    else
                    {
                        return DB::collection('issue_' . $project_key)
                                   ->where($field_key, $field['_id'])
                                   ->where('del_flg', '<>', 1)
                                   ->exists();
                    }
                case 'version':
                    if ($field['project_key'] !== $project_key)
                    {
                        return true;
                    }
                    else
                    {
                        if (!$ext_info)
                        {
                            return false;
                        }

                        $vid = $field['_id'];
                        return DB::collection('issue_' . $project_key)
                                   ->where(function ($query) use ($vid, $ext_info) {
                                       foreach ($ext_info as $key => $vf) {
                                           if ($vf['type'] === 'SingleVersion') {
                                               $query->orWhere($vf['key'], $vid);
                                           } else {
                                               $query->orWhere($vf['key'], 'like',  "%$vid%");
                                           }
                                       }
                                   })
                                   ->where('del_flg', '<>', 1)
                                   ->exists();
                    }
                default:
                    return true;
            }
        }
    }
}
