<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Project\Eloquent\Worklog;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use DB;
use App\Project\Provider;
use App\Project\Eloquent\Sprint;

class ReportController extends Controller
{
    private $issue_query_options = [
        'type', 
        'title',
        'no',
        'assignee', 
        'reporter', 
        'resolver', 
        'closer', 
        'state', 
        'resolution', 
        'priority', 
        'module',
        'resolve_version', 
        'effect_versions',
        'labels',
        'epic',
        'sprint',
        'created_at',
        'updated_at',
        'resolved_at',
        'closed_at'
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
    }

    /**
     * get permissions by project_key and roleid.
     *
     * @param  string $project_key
     * @param  string $role_id
     * @return array
     */
    public function getWorklogs(Request $request, $project_key)
    {
        $pipeline = [];

        if (array_only($request->all(), $this->issue_query_options))
        {
            $issue_ids = [];

            $query = $this->getIssueQueryBuilder($project_key, $request->all());
            $issues = $query->get([ '_id' ]);
            foreach ($issues as $issue)
            {
                $issue_ids[] = $issue['_id']->__toString();
            }
            $pipeline[] = [ '$match' => [ 'issue_id' => [ '$in' => $issue_ids ] ] ]; 
        }

        $recorded_at = $request->input('recorded_at');
        if (isset($recorded_at) && $recorded_at)
        {
            if (strpos($recorded_at, '~') !== false)
            {
                $cond = [];
                $sections = explode('~', $recorded_at);
                if ($sections[0])
                {
                    $cond['$gte'] = strtotime($sections[0]);
                }
                if ($sections[1])
                {
                    $cond['$lte'] = strtotime($sections[1] . ' 23:59:59');
                }
                if ($cond)
                {
                    $pipeline[] = [ '$match' => [ 'recorded_at' => $cond ] ];
                }
            }
            else
            {
                $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
                $unit = substr($recorded_at, -1);
                if (in_array($unit, [ 'w', 'm', 'y' ]))
                {
                    $direct = substr($recorded_at, 0, 1);
                    $val = abs(substr($recorded_at, 0, -1));
                    $time_val = strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit])));

                    $cond = [];
                    if ($direct === '-')
                    {
                        $cond['$lt'] = $time_val;
                    }
                    else
                    {
                        $cond['$gte'] = $time_val;
                    }
                    if ($cond)
                    {
                        $pipeline[] = [ '$match' => [ 'recorded_at' => $cond ] ];
                    }
                }
            }
        }

        $sprint_no = $request->input('sprint');
        if (isset($sprint_no) && $sprint_no)
        {
            $sprint = Sprint::where('project_key', $project_key)->where('no', intval($sprint_no))->first();

            $cond = [];
            $cond['$gte'] = strtotime(date('Ymd', $sprint->start_time));
            $cond['$lte'] = strtotime(date('Ymd', $sprint->complete_time) . ' 23:59:59');

            $pipeline[] = [ '$match' => [ 'recorded_at' => $cond ] ];
        }

        $pipeline[] = [ '$group' => [ '_id' => '$recorder.id', 'value' => [ '$sum' => '$spend_m' ] ] ]; 

        $ret = DB::collection('worklog')->raw(function($col) use($pipeline) {
            return $col->aggregate($pipeline);
        });

        $others_val = 0;
        $results = iterator_to_array($ret);
        $new_results = [];
        foreach ($results as $r) 
        {
            $user = Sentinel::findById($r['_id']);
            if ($user)
            {
                $new_results[] = [ 'user' => [ 'id' => $user->id, 'name' => $user->first_name ], 'value' => $r['value'] ];
            }
            else
            {
                $others_val += $r['value'];
            }
        }
        if ($others_val > 0)
        {
            $new_results[] = [ 'user' => [ 'id' => 'other', 'name' => '' ], 'value' => $other_val ];
        }

        return Response()->json([ 'ecode' => 0, 'data' => $new_results ]);
    }
}
