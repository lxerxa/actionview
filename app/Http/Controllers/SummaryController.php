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
        //$users = Provider::getUserList($project_key); 

        $issues = DB::collection('issue_' . $project_key)
            ->where('created_at', '>=', strtotime('-100 week'))
            ->get([ 'type' ]);

        $new_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($new_issues[$issue['type']]))
            {
                $new_issues[$issue['type']] = 0;
            }
            $new_issues[$issue['type']] += 1;
        }

        $issues = DB::collection('issue_' . $project_key)
            ->where('state', 'Closed')
            ->where('updated_at', '>=', strtotime('-100 week'))
            ->get([ 'type' ]);

        $closed_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($closed_issues[$issue['type']]))
            {
                $closed_issues[$issue['type']] = 0;
            }
            $closed_issues[$issue['type']] += 1;
        }

        $issues = DB::collection('issue_' . $project_key)
            ->where('resolution', 'Unresolved')
            ->get([ 'assignee', 'type' ]);

        $users = [];
        $unresolved_issues = [];
        foreach ($issues as $issue)
        {
            if (!isset($issue['assignee'])) 
            {
                continue;
            }

            $users[$issue['assignee']['id']] = $issue['assignee']['name'];

            if (!isset($unresolved_issues[$issue['assignee']['id']][$issue['type']]))
            {
                $unresolved_issues[$issue['assignee']['id']][$issue['type']] = 0;
            }
            $unresolved_issues[$issue['assignee']['id']][$issue['type']] += 1;
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'new_issues' => $new_issues, 'closed_issues' => $closed_issues, 'unresolved_issues' => $unresolved_issues ], 'options' => [ 'types' => $types, 'users' => $users, 'weekAgo' => '' ] ]);
    }
}
