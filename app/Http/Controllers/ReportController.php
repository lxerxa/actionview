<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Project\Eloquent\Worklog;
use App\Project\Eloquent\ReportFilters;

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

    private $default_filters = [
        'issue' => [
            [ 'id' => 'all_by_type', 'name' => '全部问题/按类型', 'query' => [ 'row' => 'type', 'column' => 'type' ] ], 
            [ 'id' => 'unresolved_by_assignee', 'name' => '未解决的/按经办人', 'query' => [ 'row' => 'assignee', 'column' => 'assignee', 'resolution' => 'Unresolved' ] ], 
            [ 'id' => 'unresolved_by_priority', 'name' => '未解决的/按优先级', 'query' => [ 'row' => 'priority', 'column' => 'priority', 'resolution' => 'Unresolved' ] ], 
            [ 'id' => 'unresolved_by_module', 'name' => '未解决的/按模块', 'query' => [ 'row' => 'module', 'column' => 'module', 'resolution' => 'Unresolved' ] ] 
        ], 
        'worklog' => [
            [ 'id' => 'all', 'name' => '全部填报', 'query' => [] ], 
            [ 'id' => 'in_one_month', 'name' => '过去一个月的', 'query' => [ 'recorded_at' => '1m' ] ], 
        ] 
    ];

    private $mode_enum = [ 'issue', 'trend', 'worklog', 'timetrack', 'others' ];

    /**
     * Display a listing of the resource.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $filters = $this->default_filters;

        $res = ReportFilters::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->get();
        foreach($res as $v)
        {
            if (isset($v['filters']))
            {
                $filters[$v['mode']] = $v['filters'];
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => $filters ]);
    }

    /**
     * get the mode filter.
     *
     * @param  string $project_key
     * @param  string $mode
     * @return \Illuminate\Http\Response
     */
    public function getSomeFilters($project_key, $mode)
    {
        if (!in_array($mode, $this->mode_enum))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        $filters = isset($this->default_filters[$mode]) ? $this->default_filters[$mode] : [] ;

        $res = ReportFilters::where('project_key', $project_key)
            ->where('mode', $mode)
            ->where('user', $this->user->id)
            ->first(); 
        if ($res)
        {
            $filters = isset($res->filters) ? $res->filters : [];
        }

        return Response()->json([ 'ecode' => 0, 'data' => $filters ]);
    }

    /**
     * save the custimized filter.
     *
     * @param  string $project_key
     * @param  string $mode
     * @return \Illuminate\Http\Response
     */
    public function saveFilter(Request $request, $project_key, $mode)
    {
        if (!in_array($mode, $this->mode_enum))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        $query = $request->input('query');
        if (!isset($query))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }
        
        $res = ReportFilters::where('project_key', $project_key)
            ->where('mode', $mode)
            ->where('user', $this->user->id)
            ->first();
        if ($res)
        {
            $filters = isset($res['filters']) ? $res['filters'] : [];
            array_push($filters, [ 'id' => md5(microtime()), 'name' => $name, 'query' => $query ]);
            $res->filters = $filters;
            $res->save();
        }
        else
        {
            $filters = $this->default_filters[$mode];
            array_push($filters, [ 'id' => md5(microtime()), 'name' => $name, 'query' => $query ]);
            ReportFilters::create([ 'project_key' => $project_key, 'mode' => $mode, 'user' => $this->user->id, 'filters' => $filters ]); 
        }

        return $this->getSomeFilters($project_key, $mode);
    }

    /**
     * reset the mode filters.
     *
     * @param  string $project_key
     * @param  string $mode
     * @return \Illuminate\Http\Response
     */
    public function resetSomeFilters(Request $request, $project_key, $mode)
    {
        if (!in_array($mode, $this->mode_enum))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        ReportFilters::where('project_key', $project_key)
            ->where('mode', $mode)
            ->where('user', $this->user->id)
            ->delete();
        return $this->getSomeFilters($project_key, $mode);
    }

    /**
     * edit the mode filters.
     *
     * @param  string $project_key
     * @param  string $mode
     * @return \Illuminate\Http\Response
     */
    public function editSomeFilters(Request $request, $project_key, $mode)
    {
        if (!in_array($mode, $this->mode_enum))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            $old_filters = $this->default_filters[$mode]; 

            $res = ReportFilters::where('project_key', $project_key)
                ->where('mode', $mode)
                ->where('user', $this->user->id)
                ->first();
            if ($res)
            {
                $old_filters = isset($res->filters) ? $res->filters : [];
            }
            
            $new_filters = [];
            foreach ($sequence as $id)
            {
                foreach ($old_filters as $filter)
                {
                    if ($filter['id'] === $id)
                    {
                        $new_filters[] = $filter;
                        break;
                    }
                }
            }

            if ($res)
            {
                $res->filters = $new_filters;
                $res->save();
            }
            else
            {
                ReportFilters::create([ 'project_key' => $project_key, 'mode' => $mode, 'user' => $this->user->id, 'filters' => $new_filters ]); 
            }
        }

        return $this->getSomeFilters($project_key, $mode);
    }

    /**
     * get worklog report pipeline.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getWorklogWhere($project_key, $options)
    {
        $where = [];

        if (array_only($options, $this->issue_query_options))
        {
            $issue_ids = [];

            $query = DB::collection('issue_' . $project_key)->whereRaw($this->getIssueFilter($options));
            $issues = $query->get([ '_id' ]);
            foreach ($issues as $issue)
            {
                $issue_ids[] = $issue['_id']->__toString();
            }
            $where['issue_id'] = [ '$in' => $issue_ids ];
        }

        $recorded_at = isset($options['recorded_at']) ? $options['recorded_at'] : '';
        if ($recorded_at)
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
                    $where['recorded_at'] = $cond;
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
                        $where['recorded_at'] = $cond;
                    }
                }
            }
        }

        $recorder = isset($options['recorder']) ? $options['recorder'] : '';
        if ($recorder)
        {
            $where['recorder.id'] = $recorder;
        }

        $sprint_no = isset($options['sprint']) ? $options['sprint'] : '';
        if ($sprint_no)
        {
            $sprint = Sprint::where('project_key', $project_key)->where('no', intval($sprint_no))->first();

            $cond = [];
            $cond['$gte'] = strtotime(date('Ymd', $sprint->start_time));
            $cond['$lte'] = strtotime(date('Ymd', $sprint->complete_time) . ' 23:59:59');

            $where['recorded_at'] = $cond;
        }

        $where['project_key'] = $project_key;

        return $where;
    }

    /**
     * get worklog detail report by memeber.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getWorklogList(Request $request, $project_key)
    {
        $pipeline = [];

        $where = $this->getWorklogWhere($project_key, $request->all());
        $pipeline[] = [ '$match' => $where ];

        $pipeline[] = [ '$group' => [ '_id' => '$issue_id', 'value' => [ '$sum' => '$spend_m' ] ] ];

        $ret = DB::collection('worklog')->raw(function($col) use($pipeline) {
            return $col->aggregate($pipeline);
        });

        $new_results = [];
        $results = iterator_to_array($ret);
        foreach ($results as $k => $r)
        {
            $tmp = [];
            $tmp['total_value'] = $r['value'];
            $issue = DB::collection('issue_' . $project_key)
                ->where('_id', $r['_id'])
                ->first();
            $tmp['no']      = $issue['no'];
            $tmp['title']   = $issue['title'];
            $tmp['state']   = $issue['state'];
            $tmp['type']    = $issue['type'];
            $new_results[]  = $tmp;
 
        }

        usort($new_results, function ($a, $b) { return $a['no'] <= $b['no']; });

        return Response()->json([ 'ecode' => 0, 'data' => $new_results ]);
    }

    /**
     * get worklog report by project_key.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getWorklogs(Request $request, $project_key)
    {
        $pipeline = [];

        $where = $this->getWorklogWhere($project_key, $request->all());
        $pipeline[] = [ '$match' => $where ];

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
