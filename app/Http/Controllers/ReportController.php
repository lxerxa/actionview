<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\Worklog;
use App\Project\Eloquent\ReportFilters;
use App\System\Eloquent\CalendarSingular;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use DB;
use App\Project\Provider;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\Version;

class ReportController extends Controller
{
    use TimeTrackTrait;

    private $default_filters = [
        'issues' => [
            [ 'id' => 'all_by_type', 'name' => '全部问题/按类型', 'query' => [ 'stat_x' => 'type', 'stat_y' => 'type' ] ], 
            [ 'id' => 'unresolved_by_assignee', 'name' => '未解决的/按经办人', 'query' => [ 'stat_x' => 'assignee', 'stat_y' => 'assignee', 'resolution' => 'Unresolved' ] ], 
            [ 'id' => 'unresolved_by_priority', 'name' => '未解决的/按优先级', 'query' => [ 'stat_x' => 'priority', 'stat_y' => 'priority', 'resolution' => 'Unresolved' ] ], 
            [ 'id' => 'unresolved_by_module', 'name' => '未解决的/按模块', 'query' => [ 'stat_x' => 'module', 'stat_y' => 'module', 'resolution' => 'Unresolved' ] ] 
        ], 
        'worklog' => [
            [ 'id' => 'all', 'name' => '全部填报', 'query' => [] ], 
            [ 'id' => 'in_one_month', 'name' => '过去一个月的', 'query' => [ 'recorded_at' => '1m' ] ], 
            [ 'id' => 'active_sprint', 'name' => '当前活动Sprint', 'query' => [] ], 
            [ 'id' => 'latest_completed_sprint', 'name' => '最近已完成Sprint', 'query' => [] ], 
            //[ 'id' => 'will_release_version', 'name' => '最近要发布版本', 'query' => [] ], 
            //[ 'id' => 'latest_released_version', 'name' => '最近已发布版本', 'query' => [] ], 
        ], 
        'timetracks' => [
            [ 'id' => 'all', 'name' => '全部问题', 'query' => [] ], 
            [ 'id' => 'unresolved', 'name' => '未解决的', 'query' => [ 'resolution' => 'Unresolved' ] ], 
            [ 'id' => 'active_sprint', 'name' => '当前活动Sprint', 'query' => [] ], 
            [ 'id' => 'latest_completed_sprint', 'name' => '最近已完成Sprint', 'query' => [] ], 
            //[ 'id' => 'will_release_version', 'name' => '最近要发布版本', 'query' => [] ], 
            //[ 'id' => 'latest_released_version', 'name' => '最近已发布版本', 'query' => [] ], 
        ], 
        'regressions' => [
            [ 'id' => 'all', 'name' => '已解决问题', 'query' => [] ], 
            [ 'id' => 'active_sprint', 'name' => '当前活动Sprint', 'query' => [] ], 
            [ 'id' => 'latest_completed_sprint', 'name' => '最近已完成Sprint', 'query' => [] ], 
        ],
        'trend' => [
            [ 'id' => 'day_in_one_month', 'name' => '问题每日变化趋势', 'query' => [ 'stat_time' => '1m' ] ], 
            [ 'id' => 'week_in_two_months', 'name' => '问题每周变化趋势', 'query' => [ 'stat_time' => '2m', 'interval' => 'week' ] ], 
        ]
    ];

    private $mode_enum = [ 'issues', 'trend', 'worklog', 'timetracks', 'regressions', 'others' ];

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

        foreach($filters as $mode => $some_filters)
        {
            $filters[$mode] = $this->convFilters($project_key, $some_filters);
        }

        return Response()->json([ 'ecode' => 0, 'data' => $filters ]);
    }

    /**
     * convert the filters.
     *
     * @param  array $filters
     * @return \Illuminate\Http\Response
     */
    public function convFilters($project_key, $filters)
    {
        foreach($filters as $key => $filter)
        {
            if ($filter['id'] === 'active_sprint')
            {
                $sprint = Sprint::where('project_key', $project_key)
                    ->where('status', 'active')
                    ->first();
                if ($sprint)
                {
                    $filters[$key]['query'] = [ 'sprints' => $sprint->no ];
                }
                else
                {
                    unset($filters[$key]);
                }
            }
            else if ($filter['id'] === 'latest_completed_sprint')
            {
                $sprint = Sprint::where('project_key', $project_key)
                    ->where('status', 'completed')
                    ->orderBy('no', 'desc')
                    ->first();
                if ($sprint)
                {
                    $filters[$key]['query'] = [ 'sprints' => $sprint->no ];
                }
                else
                {
                    unset($filters[$key]);
                }
            }
            else if ($filter['id'] === 'will_release_version')
            {
                $version = Version::where('project_key', $project_key)
                    ->where('status', 'unreleased')
                    ->orderBy('name', 'asc')
                    ->first();
                if ($version)
                {
                    $filters[$key]['query'] = [ 'resolve_version' => $version->id ];
                }
                else
                {
                    unset($filters[$key]);
                }
            }
            else if ($filter['id'] === 'latest_released_version')
            {
                $version = Version::where('project_key', $project_key)
                    ->where('status', 'released')
                    ->orderBy('name', 'desc')
                    ->first();
                if ($version)
                {
                    $filters[$key]['query'] = [ 'resolve_version' => $version->id ];
                }
                else
                {
                    unset($filters[$key]);
                }
            }
        }
        return array_values($filters);
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
            throw new \UnexpectedValueException('the mode value is error.', -11851);
        }

        $filters = isset($this->default_filters[$mode]) ? $this->default_filters[$mode] : []; 

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
            throw new \UnexpectedValueException('the mode value is error.', -11851);
        }

        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11852);
        }

        $query = $request->input('query');
        if (!isset($query))
        {
            throw new \UnexpectedValueException('the query can not be empty.', -11853);
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
            throw new \UnexpectedValueException('the mode value is error.', -11851);
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
            throw new \UnexpectedValueException('the mode value is error.', -11851);
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

        $issue_id = isset($options['issue_id']) ? $options['issue_id'] : '';
        if ($issue_id)
        {
            $where['issue_id'] = $issue_id;
        }
        else
        {
            $issue_ids = [];

            $query = DB::collection('issue_' . $project_key)->whereRaw($this->getIssueQueryWhere($project_key, $options));
            $issues = $query->get([ '_id' ]);
            foreach ($issues as $issue)
            {
                $issue_ids[] = $issue['_id']->__toString();
            }
            $where['issue_id'] = [ '$in' => $issue_ids ];
        }

        $cond = [];
        $recorded_at = isset($options['recorded_at']) ? $options['recorded_at'] : '';
        if ($recorded_at)
        {
            if (strpos($recorded_at, '~') !== false)
            {
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

        $sprint_no = isset($options['sprints']) ? $options['sprints'] : '';
        if ($sprint_no)
        {
            $sprint = Sprint::where('project_key', $project_key)->where('no', intval($sprint_no))->first();

            $sprint_start_time = strtotime(date('Ymd', $sprint->start_time));
            if (!isset($cond['$gte']) || $cond['$gte'] < $sprint_start_time)
            {
                $cond['$gte'] = $sprint_start_time;
            }

            if (isset($sprint->real_complete_time) && $sprint->real_complete_time > 0)
            {
                $sprint_complet_time = strtotime(date('Ymd', $sprint->real_complete_time) . ' 23:59:59');
                if (!isset($cond['$lte']) || $cond['$lte'] > $sprint_complet_time)
                {
                    $cond['$lte'] = $sprint_complet_time; 
                }
            }

            $where['recorded_at'] = $cond;
        }

        $where['project_key'] = $project_key;

        return $where;
    }

    /**
     * get worklog detail report by issue.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @return \Illuminate\Http\Response
     */
    public function getWorklogDetail(Request $request, $project_key, $issue_id)
    {
        $total = Worklog::Where('project_key', $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        $where = $this->getWorklogWhere($project_key, [ 'issue_id' => $issue_id ] + $request->all());
        $parts = Worklog::WhereRaw($where)
           ->orderBy('recorded_at', 'desc')
           ->get();

        return Response()->json(['ecode' => 0, 'data' => [ 'total' => $total, 'parts' => $parts ] ]);
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
            $tmp['id']      = $issue['_id']->__toString();
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

    /**
     * get worklog detail report by issue.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @return \Illuminate\Http\Response
     */
    public function getTimetracksDetail(Request $request, $project_key, $issue_id)
    {
        $worklogs = Worklog::Where('project_key', $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        foreach($worklogs as $worklog)
        {
            if (!isset($worklog->spend_m) || !$worklog->spend_m)
            {
                $worklog->spend_m = $this->ttHandleInM($worklog->spend ?: '');
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $worklogs ]);
    }

     /* get timetracks report by project_key.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getTimetracks(Request $request, $project_key)
    {
        $where = $this->getIssueQueryWhere($project_key, $request->all());

        $scale = $request->input('scale');
        if ($scale === 'only')
        {
            $where['$and'][] = [ 'original_estimate' => [ '$exists' => 1, '$ne' => '' ] ];
        }

        $query = DB::collection('issue_' . $project_key)->whereRaw($where);
        $issues = $query->orderBy('no', 'desc')->take(10000)->get();

        $new_issues = [];
        foreach($issues as $issue)
        {
            $issue_id = $issue['_id']->__toString();

            $tmp['id']        = $issue_id;
            $tmp['no']        = $issue['no'];
            $tmp['title']     = $issue['title'];
            $tmp['state']     = $issue['state'];
            $tmp['type']      = $issue['type'];
            $tmp['origin']    = isset($issue['original_estimate']) ? $issue['original_estimate'] : '';
            $tmp['origin_m']  = isset($issue['original_estimatei_m']) ? $issue['original_estimatei_m'] : $this->ttHandleInM($tmp['origin']);

            $spend_m = 0;
            $left_m = $tmp['origin_m'];
            $worklogs = Worklog::Where('project_key', $project_key)
                ->where('issue_id', $issue_id)
                ->orderBy('recorded_at', 'asc')
                ->get();
            foreach($worklogs as $log)
            {
                $this_spend_m = isset($log['spend_m']) ? $log['spend_m'] : $this->ttHandleInM(isset($log['spend']) ? $log['spend'] : ''); 
                $spend_m += $this_spend_m; 
                if ($log['adjust_type'] == '1')
                {
                    $left_m = $left_m === '' ? '' : $left_m - $this_spend_m;
                }
                else if ($log['adjust_type'] == '3')
                {
                    $leave_estimate = isset($log['leave_estimate']) ? $log['leave_estimate'] : '';
                    $leave_estimate_m = isset($log['leave_estimate_m']) ? $log['leave_estimate_m'] : $this->ttHandleInM($leave_estimate);
                    $left_m = $leave_estimate_m;
                }
                else if ($log['adjust_type'] == '4')
                {
                    $cut = isset($log['cut']) ? $log['cut'] : '';
                    $cut_m = isset($log['cut_m']) ? $log['cut_m'] : $this->ttHandleInM($cut);
                    $left_m = $left_m === '' ? '' : $left_m - $cut_m;
                }
            }
            $tmp['spend_m'] = $spend_m;
            $tmp['spend'] = $this->ttHandle($spend_m . 'm');

            $tmp['left_m'] = $left_m === '' ? '' : max([ $left_m, 0 ]);
            $tmp['left'] = $left_m === '' ? '' : $this->ttHandle(max([ $left_m, 0]) . 'm');

            $new_issues[] = $tmp;
        }

        return Response()->json([ 'ecode' => 0, 'data' => $new_issues ]);
    }

    /**
     * get initialized trend data.
     *
     * @param  string $interval
     * @param  number $star_stat_time
     * @param  number $end_stat_time 
     * @return \Illuminate\Http\Response
     */
    public function getInitializedTrendData($interval, $start_stat_time, $end_stat_time)
    {
        // initialize the results
        $results = [];
        $t = $end_stat_time;
        if ($interval == 'month')
        {
       	    $t = strtotime(date('Y/m/t', $end_stat_time));
        }
        else if ($interval == 'week')
        {
            $n = date('N', $end_stat_time);
            $t = strtotime(date('Y/m/d', $end_stat_time) . ' +' . (7 - $n) . ' day');
        }
        else
        {
            $t = strtotime(date('Y/m/d', $end_stat_time));
        }

        $i = 0;
        $days = [];
        while($t >= $start_stat_time && $i < 100)
        {
            $tmp = [ 'new' => 0, 'resolved' => 0, 'closed' => 0 ];
            $y = date('Y', $t);
            $m = date('m', $t);
            $d = date('d', $t);
            if ($interval == 'month')
            {
                $tmp['category'] = date('Y/m', $t);
                $t = mktime(0, 0, 0, $m - 1, $d, $y);
            }
            else if ($interval == 'week')
            {
                $tmp['category'] = date('Y/m/d', $t - 6 * 24 * 3600);
                $t = mktime(0, 0, 0, $m, $d - 7, $y);
            }
            else
            {
                $tmp['category'] = date('Y/m/d', $t);

                $days[] = $tmp['category'];
                $week_flg = intval(date('w', $t));
                $tmp['notWorking'] = ($week_flg === 0 || $week_flg === 6) ? 1 : 0;

                $t = mktime(0, 0, 0, $m, $d - 1, $y);
            }
            $results[$tmp['category']] = $tmp;
            $i++;
        }

        if ($days)
        {
            $singulars = CalendarSingular::where([ 'date' => [ '$in' => $days ] ])->get();
            foreach ($singulars as $singular)
            {
                $results[$singular->day]['notWorking'] = $singular->type == 'holiday' ? 1 : 0;
            }
        }

        return array_reverse($results);
    }


    /**
     * get trend report by project_key.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getTrends(Request $request, $project_key)
    {
    	$interval = $request->input('interval') ?: 'day';
    	if (!in_array($interval, [ 'day', 'week', 'month' ]))
    	{
    	    throw new \UnexpectedValueException('the name can not be empty.', -11852);
    	}

    	$is_accu = $request->input('is_accu') === '1' ? true : false;

    	$project = Project::where('key', $project_key)->first();
    	if (!$project)
    	{
    	    throw new \UnexpectedValueException('the project is not exists.', -11850);
    	}

    	$start_stat_time = strtotime($project->created_at);
    	$end_stat_time = time();

    	$where = $this->getIssueQueryWhere($project_key, $request->all());

        $stat_time = $request->input('stat_time');
        if (isset($stat_time) && $stat_time)
        {
            $or = [];
            if (strpos($stat_time, '~') !== false)
            {
                $cond = [];
                $sections = explode('~', $stat_time);
                if ($sections[0])
                {
                    $cond['$gte'] = strtotime($sections[0]);
                    $start_stat_time = max([ $start_stat_time, $cond['$gte'] ]);
                }
                if ($sections[1])
                {
                    $cond['$lte'] = strtotime($sections[1] . ' 23:59:59');
                    $end_stat_time = min([ $end_stat_time, $cond['$lte'] ]);
                }
                if ($cond)
                {
                    $or[] = [ 'created_at' =>  $cond ];
                    $or[] = [ 'resolved_at' =>  $cond ];
                    $or[] = [ 'closed_at' => $cond ];
                }
            }
            else
            {
                $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
                $unit = substr($stat_time, -1);
                if (in_array($unit, [ 'w', 'm', 'y' ]))
                {
                    $direct = substr($stat_time, 0, 1);
                    $val = abs(substr($stat_time, 0, -1));
                    $time_val = strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit])));
                    $cond = [];
                    if ($direct === '-')
                    {
                        $cond['$lt'] = $time_val;
                        $end_stat_time = min([ $end_stat_time, $cond['$lt'] ]);
                    }
                    else
                    {
                        $cond['$gte'] = $time_val;
                        $start_stat_time = max([ $start_stat_time, $cond['$gte'] ]);
                    }
                    if ($cond)
                    {
                        $or[] = [ 'created_at' =>  $cond ];
                        $or[] = [ 'resolved_at' =>  $cond ];
                        $or[] = [ 'closed_at' => $cond ];
                    }
                }
            }

            if (!$is_accu && $or)
            {
            	$where['$and'][] = [ '$or' => $or ];
            }
            else
            {
            	$where['$and'][] = [ 'created_at' => [ '$lte' => $end_stat_time ] ];
            }
        }

        $results = $this->getInitializedTrendData($interval, $start_stat_time, $end_stat_time);

        $query = DB::collection('issue_' . $project_key)->whereRaw($where);
        $issues = $query->get([ 'created_at', 'resolved_at', 'closed_at' ]);

        foreach ($issues as $issue)
        {
            if (isset($issue['created_at']) && $issue['created_at'])
            {
                $created_date = $this->convDate($interval, $issue['created_at']);; 
                if ($is_accu)
                {
                    foreach($results as $key => $val)
                    {
                        if ($key >= $created_date)
                        {
                            $results[$key]['new'] += 1;
                        }
                    }
                }
                else if (isset($results[$created_date]) && $issue['created_at'] >= $start_stat_time)
                {
                    $results[$created_date]['new'] += 1;
                }
            }
            if (isset($issue['resolved_at']) && $issue['resolved_at'])
            {
                $resolved_date = $this->convDate($interval, $issue['resolved_at']);
                if ($is_accu)
                {
                    foreach($results as $key => $val)
                    {
                        if ($key >= $resolved_date)
                        {
                            $results[$key]['resolved'] += 1;
                        }
                    }
                }
                else if (isset($results[$resolved_date]) && $issue['resolved_at'] >= $start_stat_time)
                {
                    $results[$resolved_date]['resolved'] += 1;
                }
            }
            if (isset($issue['closed_at']) && $issue['closed_at'])
            {
                $closed_date = $this->convDate($interval, $issue['closed_at']);
                if ($is_accu)
                {
                    foreach($results as $key => $val)
                    {
                        if ($key >= $closed_date)
                        {
                            $results[$key]['closed'] += 1;
                        }
                    }
                }
                else if (isset($results[$closed_date]) && $issue['closed_at'] >= $start_stat_time)
                {
                    $results[$closed_date]['closed'] += 1;
                }
            }
        }

        return Response()->json([ 
            'ecode' => 0, 
            'data' => array_values($results), 
            'options' => [ 'trend_start_stat_date' => date('Y/m/d', $start_stat_time), 'trend_end_stat_date' => date('Y/m/d', $end_stat_time) ] 
        ]);
    }

    /**
     * get converted date.
     *
     * @param  string $interval
     * @param  number $at
     * @return string 
     */
    public function convDate($interval, $at)
    {
        if ($interval === 'week')
        {
            $n = date('N', $at);
            return date('Y/m/d', $at - ($n - 1) * 24 * 3600); 
        }
        else if ($interval === 'month')
        {
            return date('Y/m', $at); 
        }
        else
        {
            return date('Y/m/d', $at); 
        }
    }

    public function arrangeRegressionData($data, $project_key, $dimension)
    {
        if (!$dimension)
        {
            return $data;
        }

        $results = [];
        if (in_array($dimension, ['reporter', 'assignee', 'resolver', 'closer']))
        {
            foreach ($data as $value)
            {
                $tmp = [];
                $tmp['ones'] = $value['ones'];
                $tmp['gt_ones'] = $value['gt_ones'];
                $tmp['category'] = $value['name'];
                $results[] = $tmp;
            }
        }
        else if (in_array($dimension, ['type', 'priority', 'module', 'resolve_version', 'epic']))
        {
            if ($dimension === 'module')
            {
                $modules = Provider::getModuleList($project_key, ['name']);
                foreach ($modules as $m)
                {
                    foreach ($data as $key => $val)
                    {
                        if ($key === $m->id)
                        {
                            $val['category'] = $m->name;
                            $results[$m->id] = $val;
                            break;
                        }
                    }
                }
            }
            else if ($dimension === 'resolve_version')
            {
                $versions = Provider::getVersionList($project_key, ['name']);
                foreach ($versions as $v)
                {
                    foreach ($data as $key => $val)
                    {
                        if ($key === $v->id)
                        {
                            $val['category'] = $v->name;
                            $results[$key] = $val;
                            break;
                        }
                    }
                }
            }
            else if ($dimension === 'type')
            {
                $types = Provider::getTypeList($project_key, ['name']);
                foreach ($types as $v)
                {
                    foreach ($data as $key => $val)
                    {
                        if ($key === $v->id)
                        {
                            $val['category'] = $v->name;
                            $results[$key] = $val;
                            break;
                        }
                    }
                }                
            }
            else if ($dimension === 'priority')
            {
                $priorities = Provider::getPriorityOptions($project_key, ['name']);
                foreach ($priorities as $v)
                {
                    foreach ($data as $key => $val)
                    {
                        if ($key === $v['_id'])
                        {
                            $val['category'] = $v['name'];
                            $results[$key] = $val;
                            break;
                        }
                    }
                }
            }
            else if ($dimension === 'epic')
            {
                $epics = Provider::getEpicList($project_key, ['name']);
                foreach ($epics as $v)
                {
                    foreach ($data as $key => $val)
                    {
                        if ($key === $v['_id'])
                        {
                            $val['category'] = $v['name'];
                            $results[$key] = $val;
                            break;
                        }
                    }
                }
            }

            //$others = [ 'ones' => [], 'gt_ones' => [], 'category' => 'others' ];
            //foreach ($data as $key => $val) 
            //{
            //    if (!isset($results[$key]))
            //    {
            //        $others['ones'] = array_unique(array_merge($others['ones'], $val['ones']));
            //        $others['gt_ones'] = array_unique(array_merge($others['gt_ones'], $val['gt_ones']));
            //    }
            //}

            //if ($others['ones'] && $others['gt_ones'])
            //{
            //    $results['others'] = $others;
            //}
        }
        else if ($dimension === 'sprints')
        {
            foreach ($data as $key => $val) 
            {
                $tmp = [];
                $tmp['ones'] = $val['ones'];
                $tmp['gt_ones'] = $val['gt_ones'];
                $tmp['category'] = 'Sprint ' . $key;
                $results[] = $tmp;
            }           
        }
        else if ($dimension === 'labels')
        {
            foreach ($data as $key => $val) 
            {
                $tmp = [];
                $tmp['ones'] = $val['ones'];
                $tmp['gt_ones'] = $val['gt_ones'];
                $tmp['category'] = $key;
                $results[] = $tmp;
            }             
        }
        else
        {
            $fields = Provider::getFieldList($project_key);
            foreach ($fields as $field)
            {
                if ($field->key === $dimension)
                {
                    if (isset($field->optionValues) && $field->optionValues)
                    {
                        foreach($field->optionValues as $v)
                        {
                            foreach ($data as $key => $val)
                            {
                                if ($key === $v['id'])
                                {
                                    $val['category'] = $v['name'];
                                    $results[$key] = $val;
                                    break;
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }

        return array_values($results);
    }

    /* get resolution regression report by project_key.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function getRegressions(Request $request, $project_key)
    {
        $where = $this->getIssueQueryWhere($project_key, $request->all());

        $his_resolvers = $request->input('his_resolvers') ? explode(',', $request->input('his_resolvers')) : [];
        $or = [];
        foreach ($his_resolvers as $resolver)
        {
            $or[] = [ 'his_resolvers' => $resolver ];
        }
        if ($or)
        {
            $where['$and'][] = [ '$or' => $or ];
        }

        $where['$and'][] = [ 'regression_times' => [ '$exists' => 1 ] ];

        $sprint_start_time = $sprint_complete_time = 0;
        $sprint_no = $request->input('sprint') ?: '';
        if ($sprint_no)
        {
            $sprint = Sprint::where('project_key', $project_key)->where('no', intval($sprint_no))->first();

            $sprint_start_time = strtotime(date('Ymd', $sprint->start_time));
            if (isset($sprint->real_complete_time) && $sprint->real_complete_time > 0)
            {
                $sprint_complete_time = $sprint->real_complete_time;
            }
        }

        $dimension = $request->input('stat_dimension') ?: '';
        if ($dimension)
        {
            $results = [];
        }
        else
        {
            $results = [ 'ones' => [], 'gt_ones' => [] ];
        }

        $results = [];
        $query = DB::collection('issue_' . $project_key)->whereRaw($where);
        $issues = $query->orderBy('no', 'desc')->take(1000)->get();
        foreach($issues as $issue)
        {
            $no = $issue['no'];
            $regression_times = $issue['regression_times'];
            $resolved_logs = isset($issue['resolved_logs']) ? $issue['resolved_logs'] : [];

            if ($sprint_start_time > 0)
            {
                $tmp_log = [];
                foreach($resolved_logs as $rl)
                {
                    if ($rl['at'] > $sprint_start_time && ($sprint_complete_time <= 0 || $rl['at'] < $sprint_complete_time))
                    {
                        if ($his_resolvers && !in_array($rl['user']['id'], $his_resolvers))
                        {
                            continue;
                        }
                        else
                        {
                            $tmp_log[] = $rl;
                        }
                    }
                }
                if (!$tmp_log)
                {
                    continue;
                }

                $resolved_logs = $tmp_log;
                $regression_times = count($tmp_log);
            }

            if ($dimension == 'resolver')
            {
                $log_cnt = count($resolved_logs);
                $tmp_uids = [];
                foreach ($resolved_logs as $key => $log) 
                {
                    $uid = $log['user']['id'];
                    if (in_array($uid, $tmp_uids))
                    {
                        continue;
                    }
                    else
                    {
                        $tmp_uids[] = $uid;
                    }
                    
                    if (!isset($results[$uid]))
                    {
                        $results[$uid] = [ 'ones' => [], 'gt_ones' => [], 'name' => isset($log['user']['name']) ? $log['user']['name'] : '' ];
                    }
                    if ($regression_times > 1)
                    {
                        if ($key == $log_cnt - 1)
                        {
                            $results[$uid]['ones'][] = $no;
                        }
                        else
                        {
                            $results[$uid]['gt_ones'][] = $no;
                        }
                    }
                    else
                    {
                        $results[$uid]['ones'][] = $no;
                    }
                }
            }
            else if ($dimension)
            {
                $dimension_values = isset($issue[$dimension]) ? $issue[$dimension] : '';
                if ($dimension_values && is_array($dimension_values))
                {
                    if (isset($dimension_values['id']))
                    {
                        if (!isset($results[$dimension_values['id']]))
                        {
                            $results[$dimension_values['id']] = [ 'ones' => [], 'gt_ones' => [], 'name' => isset($dimension_values['name']) ? $dimension_values['name'] : '' ];
                        }
                        if ($regression_times > 1)
                        {
                            $results[$dimension_values['id']]['gt_ones'][] = $no;
                        }
                        else
                        {
                            $results[$dimension_values['id']]['ones'][] = $no;
                        }
                    }
                    else
                    {
                        foreach($dimension_values as $val)
                        {
                            if (!isset($results[$val]))
                            {
                                $results[$val] = [ 'ones' => [], 'gt_ones' => [] ];
                            }

                            if ($regression_times > 1)
                            {
                                $results[$val]['gt_ones'][] = $no;
                            }
                            else
                            {
                                $results[$val]['ones'][] = $no;
                            }
                        }                        
                    }
                }
                else if ($dimension_values)
                {
                    if (!isset($results[$dimension_values]))
                    {
                        $results[$dimension_values] = [ 'ones' => [], 'gt_ones' => [] ];
                    }

                    if ($regression_times > 1)
                    {
                        $results[$dimension_values]['gt_ones'][] = $no;
                    }
                    else
                    {
                        $results[$dimension_values]['ones'][] = $no;
                    }
                }
            }
            else
            {
                if (!isset($results[0]))
                {
                    $results[0] = [ 'ones' => [], 'gt_ones' => [] ];
                }
                if ($regression_times > 1)
                {
                    $results[0]['gt_ones'][] = $no;
                }
                else
                {
                    $results[0]['ones'][] = $no;
                }
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => $this->arrangeRegressionData($results, $project_key, $dimension) ]);
    }

    /* init the X,Y data by dimension.
     *
     * @param  string $project_key
     * @param  string $dimension
     * @return array 
     */
    public function initXYData($project_key, $dimension)
    {
        $results = [];

        switch ($dimension) {

            case 'type':
                $types = Provider::getTypeList($project_key, ['name']);
                foreach ($types as $key => $value) 
                {
                    $results[$value->id] = [ 'name' => $value->name, 'nos' => [] ];
                }
                break;

            case 'priority':
                $priorities = Provider::getPriorityOptions($project_key, ['name']);
                foreach ($priorities as $key => $value) 
                {
                    $results[$value['_id']] = [ 'name' => $value['name'], 'nos' => [] ];
                }
                break;

            case 'state':
                $states = Provider::getStateOptions($project_key, ['name']);
                foreach ($states as $key => $value)
                {
                    $results[$value['_id']] = [ 'name' => $value['name'], 'nos' => [] ];
                }
                break;

            case 'resolution':
                $resolutions = Provider::getResolutionOptions($project_key, ['name']);
                foreach ($resolutions as $key => $value)
                {
                    $results[$value['_id']] = [ 'name' => $value['name'], 'nos' => [] ];
                }
                break;

            case 'module':
                $modules = Provider::getModuleList($project_key, ['name']);
                foreach ($modules as $key => $value) 
                {
                    $results[$value->id] = [ 'name' => $value->name, 'nos' => [] ];
                }
                break;

            case 'resolve_version':
                $versions = Provider::getVersionList($project_key, ['name']);
                foreach ($versions as $key => $value) 
                {
                    $results[$value->id] = [ 'name' => $value->name, 'nos' => [] ];
                }
                $results = array_reverse($results);
                break;

            case 'epic':
                $epics = Provider::getEpicList($project_key, ['name']);
                foreach ($epics as $key => $value) 
                {
                    $results[$value['_id']] = [ 'name' => $value['name'], 'nos' => [] ];
                }
                break; 

            case 'sprints':
                $sprints = Sprint::where('project_key', $project_key)
                    ->whereIn('status', [ 'active', 'completed' ])
                    ->orderBy('no', 'asc')
                    ->get();
                foreach($sprints as $s)
                {
                    $results[$s->no] = [ 'name' => 'Sprint' . $s->no, 'nos' => [] ];
                }
                break;

            default:
                $fields = Provider::getFieldList($project_key);
                foreach ($fields as $field) 
                {
                    if ($field->key === $dimension)
                    {
                        if (isset($field->optionValues) && $field->optionValues)
                        {
                            foreach($field->optionValues as $val)
                            {
                                $results[$val['id']] = [ 'name' => $val['name'], 'nos' => [] ];
                            }
                        }
                        break;
                    }
                }
                # code...
                break;
        }

        return $results;
    }

    /* get the issues by X,Y dimensions.
     *
     * @param  string $project_key
     * @param  string $dimension
     * @return \Illuminate\Http\Response
     */
    public function getIssues(Request $request, $project_key)
    {
        $X = $request->input('stat_x') ?: '';
        if (!$X)
        {
            throw new \UnexpectedValueException('the currentX can not be empty.', -11855);
        }
        //$X = $X === 'sprint' ? 'sprints' : $X;

        $Y = $request->input('stat_y') ?: '';
        //$Y = $Y === 'sprint' ? 'sprints' : $Y;
     

        $XYData = [];
        $YAxis = [];
        if ($X === $Y || !$Y)
        {
            $XYData[$X] = $this->initXYData($project_key, $X);
        }
        else
        {
            $XYData[$X] = $this->initXYData($project_key, $X);
            $XYData[$Y] = $this->initXYData($project_key, $Y);
        }

        $where = $this->getIssueQueryWhere($project_key, $request->all());
        $query = DB::collection('issue_' . $project_key)->whereRaw($where);
        $issues = $query->orderBy('no', 'desc')->take(10000)->get();

        foreach($issues as $issue)
        {
            foreach ($XYData as $dimension => $z)
            {
                if (!isset($issue[$dimension]) || !$issue[$dimension])
                {
                    continue;
                }

                $issue_vals = [];
                if (is_string($issue[$dimension]))
                {
                    if (strpos($issue[$dimension], ',') !== false)
                    {
                        $issue_vals = explode(',', $issue[$dimension]);
                    }
                    else
                    {
                        $issue_vals = [ $issue[$dimension] ];
                    }
                }
                else if (is_array($issue[$dimension]))
                {
                    $issue_vals = $issue[$dimension];
                    if (isset($issue[$dimension]['id']))
                    {
                        $issue_vals = [ $issue[$dimension] ];
                    }
                }

                foreach ($issue_vals as $issue_val)
                {
                    $tmpv = $issue_val;
                    if (is_array($issue_val) && isset($issue_val['id']))
                    {
                        $tmpv = $issue_val['id'];
                    }

                    if (isset($z[$tmpv]))
                    {
                        $XYData[$dimension][$tmpv]['nos'][] = $issue['no'];
                    }
                    else if ((is_array($issue_val) && isset($issue_val['id'])) || $dimension === 'labels')
                    {
                        if ($dimension === $Y && $X !== $Y)
                        {
                            $YAxis[$tmpv] = isset($issue[$dimension]['name']) ? $issue[$dimension]['name'] : $tmpv;
                        }

                        $XYData[$dimension][$tmpv] = [ 'name' => isset($issue[$dimension]['name']) ? $issue[$dimension]['name'] : $tmpv, 'nos' => [ $issue['no'] ] ];
                    }
                }
            }
        }

        $results = [];
        if ($X === $Y || !$Y)
        {
            foreach ($XYData[$X] as $key => $value)
            {
                $results[] = [ 'id' => $key, 'name' => $value['name'], 'cnt' => count($value['nos']) ];
            }
        }
        else
        {
            foreach ($XYData[$X] as $key => $value)
            {
                $results[$key] = [ 'id' => $key, 'name' => $value['name'], 'y' => [] ];
                $x_cnt = 0;

                if ($YAxis)
                {
                    foreach ($YAxis as $yai => $yav) 
                    {
                        if (isset($XYData[$Y][$yai]))
                        {
                            $y_cnt = count(array_intersect($value['nos'], $XYData[$Y][$yai]['nos']));
                            $results[$key]['y'][] = [ 'id' => $yai, 'name' => $yav, 'cnt' => $y_cnt ];
                            $x_cnt += $y_cnt;
                        }
                        else
                        {
                            $results[$key]['y'][] = [ 'id' => $yai, 'name' => yav, 'cnt' => 0 ];
                        }
                    }
                }
                else
                {
                    foreach ($XYData[$Y] as $key2 => $value2)
                    {
                        $y_cnt = count(array_intersect($value['nos'], $value2['nos']));

                        $results[$key]['y'][] = [ 'id' => $key2, 'name' => $value2['name'], 'cnt' => $y_cnt ];
                        $x_cnt += $y_cnt;
                    }
                 }
                 $results[$key]['cnt'] = $x_cnt;
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' =>  array_values($results) ]);
    }
}
