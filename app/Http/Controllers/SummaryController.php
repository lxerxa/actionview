<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use DB;
use App\Project\Provider;

class SummaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $types = Provider::getTypeList($project_key); 
        
        $optPriorities = [];
        $priorities = Provider::getPriorityList($project_key); 
        foreach ($priorities as $priority)
        {
            if (isset($priority['key']))
            {
                $optPriorities[$priority['key']] = $priority['name'];
            }
            else
            {
                $optPriorities[$priority['_id']] = $priority['name'];
            }
        }

        $optModules = [];
        $modules = Provider::getModuleList($project_key);
        foreach ($modules as $module)
        {
            $optModules[$module->id] = $module->name;
        }

        //$users = Provider::getUserList($project_key); 

        $issues = DB::collection('issue_' . $project_key)
            ->where('created_at', '>=', strtotime(date('Ymd', strtotime('-1 week'))))
            ->where('del_flg', '<>', 1)
            ->get([ 'type' ]);

        $new_issues = [ 'total' => 0 ];
        foreach ($issues as $issue)
        {
            if (!isset($new_issues[$issue['type']]))
            {
                $new_issues[$issue['type']] = 0;
            }
            $new_issues[$issue['type']] += 1;
            $new_issues['total'] += 1;
        }

        $issues = DB::collection('issue_' . $project_key)
            ->where('state', 'Closed')
            ->where('updated_at', '>=', strtotime(date('Ymd', strtotime('-1 week'))))
            ->where('del_flg', '<>', 1)
            ->get([ 'type' ]);

        $closed_issues = [ 'total' => 0 ];
        foreach ($issues as $issue)
        {
            if (!isset($closed_issues[$issue['type']]))
            {
                $closed_issues[$issue['type']] = 0;
            }
            $closed_issues['total'] += 1;
            $closed_issues[$issue['type']] += 1;
        }

        $new_percent = $closed_percent = 0;
        if ($new_issues['total'] > 0 || $closed_issues['total'] > 0)
        {
            $new_percent = $new_issues['total'] * 100 / ($new_issues['total'] + $closed_issues['total']);
            if ($new_percent > 0 && $new_percent < 1)
            {
                $new_percent = 1;
            }
            else 
            {
                $new_percent = floor($new_percent);
            }
            $closed_percent = 100 - $new_percent;
        }
        
        $new_issues['percent'] = $new_percent;
        $closed_issues['percent'] = $closed_percent;

        $issues = DB::collection('issue_' . $project_key)
            ->where('resolution', 'Unresolved')
            ->where('del_flg', '<>', 1)
            ->get([ 'priority', 'assignee', 'type', 'module' ]);

        $users = [];
        $assignee_unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($issue['assignee']) || !$issue['assignee']) 
            {
                continue;
            }

            $users[$issue['assignee']['id']] = $issue['assignee']['name'];
            if (!isset($assignee_unresolved_issues[$issue['assignee']['id']][$issue['type']]))
            {
                $assignee_unresolved_issues[$issue['assignee']['id']][$issue['type']] = 0;
            }
            if (!isset($assignee_unresolved_issues[$issue['assignee']['id']]['total']))
            {
                $assignee_unresolved_issues[$issue['assignee']['id']]['total'] = 0;
            }
            $assignee_unresolved_issues[$issue['assignee']['id']][$issue['type']] += 1;
            $assignee_unresolved_issues[$issue['assignee']['id']]['total'] += 1;
        }
        $assignee_unresolved_issues = $this->calPercent($assignee_unresolved_issues);

        $priority_unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($issue['priority']) || !$issue['priority'])
            {
                $priority_id = '-1';
            }
            else
            {
                $priority_id = $optPriorities[$issue['priority']] ? $issue['priority'] : '-1'; 
            }

            if (!isset($priority_unresolved_issues[$priority_id][$issue['type']]))
            {
                $priority_unresolved_issues[$priority_id][$issue['type']] = 0;
            }
            if (!isset($priority_unresolved_issues[$priority_id]['total']))
            {
                $priority_unresolved_issues[$priority_id]['total'] = 0;
            }
            $priority_unresolved_issues[$priority_id][$issue['type']] += 1;
            $priority_unresolved_issues[$priority_id]['total'] += 1;
        }

        $sorted_priority_unresolved_issues = [];
        foreach ($optPriorities as $key => $val)
        {
            if (isset($priority_unresolved_issues[$key]))
            {
                $sorted_priority_unresolved_issues[$key] = $priority_unresolved_issues[$key];
            }
        }
        if (isset($priority_unresolved_issues['-1'])) {
            $sorted_priority_unresolved_issues['-1'] = $priority_unresolved_issues['-1'];
        }

        $sorted_priority_unresolved_issues = $this->calPercent($sorted_priority_unresolved_issues);

        $module_unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($issue['module']) || !$issue['module'])
            {
                $module_id = '-1';
            }
            else
            {
                $module_id = isset($optModules[$issue['module']]) ? $issue['module'] : '-1';
            }

            if (!isset($module_unresolved_issues[$module_id][$issue['type']]))
            {
                $module_unresolved_issues[$module_id][$issue['type']] = 0;
            }
            if (!isset($module_unresolved_issues[$module_id]['total']))
            {
                $module_unresolved_issues[$module_id]['total'] = 0;
            }
            $module_unresolved_issues[$module_id][$issue['type']] += 1;
            $module_unresolved_issues[$module_id]['total'] += 1;
        }

        $sorted_module_unresolved_issues = [];
        foreach ($optModules as $key => $val)
        {   
            if (isset($module_unresolved_issues[$key]))
            {   
                $sorted_module_unresolved_issues[$key] = $module_unresolved_issues[$key];
            }
        }
        if (isset($module_unresolved_issues['-1'])) {
            $sorted_module_unresolved_issues['-1'] = $module_unresolved_issues['-1'];
        }

        $sorted_module_unresolved_issues = $this->calPercent($sorted_module_unresolved_issues);

        return Response()->json([ 
            'ecode' => 0, 
            'data' => [ 
                'new_issues' => $new_issues, 
                'closed_issues' => $closed_issues, 
                'assignee_unresolved_issues' => $assignee_unresolved_issues, 
                'priority_unresolved_issues' => $sorted_priority_unresolved_issues, 
                'module_unresolved_issues' => $sorted_module_unresolved_issues ], 
            'options' => [ 
                'types' => $types, 
                'users' => $users, 
                'priorities' => $optPriorities, 
                'modules' => $optModules, 
                'weekAgo' => date('m/d', strtotime('-1 week')) 
            ] 
        ]);
    }

    function calPercent($arr)
    {
        $total = 0;
        $counts = [];
        $quotients = [];
        $remainders = [];

        foreach ($arr as $key => $val)
        {
            $counts[$key] = isset($val['total']) && $val['total'] ? $val['total'] : 0;
            $total += $counts[$key];
        }

        foreach ($counts as $key => $count)
        {
            $quotient = $count * 100 / $total;
            if ($quotient > 0 && $quotient <= 1)
            {
                $quotients[$key] = 1;
            }
            else
            {
                $quotients[$key] = floor($quotient);
            }
            $remainders[$key] = ($count * 100) % $total;
        }

        $sum = array_sum($quotients);
        if ($sum < 100)
        {
            $less = 100 - $sum;
            arsort($remainders);

            $i = 1;
            foreach ($remainders as $key => $remainder)
            {
                $quotients[$key] += 1;
                if ($i >= $less)
                {
                    break;
                }
                $i++;
            }
        }

        foreach ($arr as $key => $val)
        {
            $arr[$key]['percent'] = $quotients[$key];
        }
        return $arr;
    }
}
