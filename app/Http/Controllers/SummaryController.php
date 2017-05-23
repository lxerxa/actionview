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
        //$users = Provider::getUserList($project_key); 

        $issues = DB::collection('issue_' . $project_key)
            ->where('created_at', '>=', strtotime(date('Ymd', strtotime('-100 week'))))
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
            ->where('updated_at', '>=', strtotime(date('Ymd', strtotime('-100 week'))))
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

        $issues = DB::collection('issue_' . $project_key)
            ->where('resolution', 'Unresolved')
            ->where('del_flg', '<>', 1)
            ->get([ 'priority', 'assignee', 'type' ]);

        $users = [];
        $assignee_unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($issue['assignee'])) 
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

        $priority_unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($priority_unresolved_issues[$issue['priority']][$issue['type']]))
            {
                $priority_unresolved_issues[$issue['priority']][$issue['type']] = 0;
            }
            if (!isset($priority_unresolved_issues[$issue['priority']]['total']))
            {
                $priority_unresolved_issues[$issue['priority']]['total'] = 0;
            }
            $priority_unresolved_issues[$issue['priority']][$issue['type']] += 1;
            $priority_unresolved_issues[$issue['priority']]['total'] += 1;
        }

        $sorted_priority_unresolved_issues = [];
        foreach ($optPriorities as $key => $val)
        {
            if (isset($priority_unresolved_issues[$key]))
            {
                $sorted_priority_unresolved_issues[$key] = $priority_unresolved_issues[$key];
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'new_issues' => $new_issues, 'closed_issues' => $closed_issues, 'assignee_unresolved_issues' => $assignee_unresolved_issues, 'priority_unresolved_issues' => $sorted_priority_unresolved_issues ], 'options' => [ 'types' => $types, 'users' => $users, 'priorities' => $optPriorities, 'weekAgo' => date('Y/m/d', strtotime('-100 week')) ] ]);
    }
}
