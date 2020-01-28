<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\SprintDayLog;
use App\Project\Eloquent\Board;
use App\Project\Provider;
use App\Events\SprintEvent;
use App\System\Eloquent\CalendarSingular;
use DB;

class SprintController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:manage_project', [ 'except' => [ 'show', 'getLog' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $sprint_count = Sprint::where('project_key', $project_key)->count();
        $sprint = Sprint::create([ 'project_key' => $project_key, 'no' => $sprint_count + 1, 'status' => 'waiting', 'issues' => [] ]);
        return Response()->json(['ecode' => 0, 'data' => $this->getValidSprintList($project_key)]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function moveIssue(Request $request, $project_key)
    {
        $issue_no = $request->input('issue_no');
        if (!$issue_no)
        {
            throw new \UnexpectedValueException('the moved issue cannot be empty', -11700);
        }

        $src_sprint_no = $request->input('src_sprint_no');
        if (!isset($src_sprint_no))
        {
            throw new \UnexpectedValueException('the src sprint of moved issue cannot be empty', -11701);
        }
        $src_sprint_no = intval($src_sprint_no);

        $dest_sprint_no = $request->input('dest_sprint_no');
        if (!isset($dest_sprint_no))
        {
            throw new \UnexpectedValueException('the dest sprint of moved issue cannot be empty', -11702);
        }
        $dest_sprint_no = intval($dest_sprint_no);

        if ($src_sprint_no > 0)
        {
            $src_sprint = Sprint::where('project_key', $project_key)->where('no', $src_sprint_no)->first(); 
            if (!in_array($issue_no, $src_sprint->issues ?: []))
            {
                throw new \UnexpectedValueException('the moved issue cannot be found in the src sprint', -11703);
            }
            if ($src_sprint->status == 'completed')
            {
                throw new \UnexpectedValueException('the moved issue cannot be moved into or moved out of the completed sprint', -11706);
            }
            $src_sprint->fill([ 'issues' => array_values(array_diff($src_sprint->issues, [ $issue_no ]) ?: []) ])->save();

            if ($src_sprint->status == 'active')
            {
                $this->popSprint($project_key, $issue_no, $src_sprint_no);
            }
        }

        if ($dest_sprint_no > 0)
        {
            $dest_sprint = Sprint::where('project_key', $project_key)->where('no', $dest_sprint_no)->first();
            if (in_array($issue_no, $dest_sprint->issues ?: []))
            {
                throw new \UnexpectedValueException('the moved issue has been in the dest sprint', -11704);
            }
            if ($dest_sprint->status == 'completed')
            {
                throw new \UnexpectedValueException('the moved issue cannot be moved into or moved out of the completed sprint', -11706);
            }
            $dest_sprint->fill([ 'issues' => array_values(array_merge($dest_sprint->issues ?: [], [ $issue_no ])) ])->save();

            if ($dest_sprint->status == 'active')
            {
                $this->pushSprint($project_key, $issue_no, $dest_sprint_no);
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => $this->getValidSprintList($project_key) ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $no)
    {
        $sprint = Sprint::where('project_key', $project_key)->where('no', intval($no))->first();
        return Response()->json(['ecode' => 0, 'data' => $sprint]);
    }

    /**
     * publish the sprint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function publish(Request $request, $project_key, $no)
    {
        if (!$no)
        {
            throw new \UnexpectedValueException('the published sprint cannot be empty', -11705);
        }
        $no = intval($no);

        $active_sprint_exists = Sprint::where('project_key', $project_key)
            ->where('status', 'active')
            ->exists();
        if ($active_sprint_exists)
        {
            throw new \UnexpectedValueException('the active sprint has been exists.', -11706);
        }

        $before_waiting_sprint_exists = Sprint::where('project_key', $project_key)
            ->where('status', 'waiting')
            ->where('no', '<', $no)
            ->exists();
        if ($before_waiting_sprint_exists)
        {
            throw new \UnexpectedValueException('the more first sprint has been exists.', -11707);
        }

        $sprint = Sprint::where('project_key', $project_key)
            ->where('no', $no)
            ->first();
        if (!$sprint || $project_key != $sprint->project_key)
        {
            throw new \UnexpectedValueException('the sprint does not exist or is not in the project.', -11708);
        }

        $updValues = [ 'status' => 'active' ];

        $start_time = $request->input('start_time');
        if (isset($start_time) && $start_time)
        {
            $updValues['start_time'] = $start_time;
        }
        else
        {
            throw new \UnexpectedValueException('the sprint start time cannot be empty.', -11709);
        }

        $complete_time = $request->input('complete_time');
        if (isset($complete_time) && $complete_time)
        {
            $updValues['complete_time'] = $complete_time;
        }
        else
        {
            throw new \UnexpectedValueException('the sprint complete time cannot be empty.', -11710);
        }

        $description = $request->input('description');
        if (isset($description) && $description)
        {
            $updValues['description'] = $description;
        }

        $kanban_id = $request->input('kanban_id');
        if (!isset($kanban_id) || !$kanban_id)
        {
            throw new \UnexpectedValueException('the kanban id cannot be empty', -11717);
        }

        $new_issues = $this->filterIssues($project_key, isset($sprint->issues) ? $sprint->issues : [], $kanban_id);

        $updValues['issues'] = $new_issues['issues'];
        $updValues['origin_issues'] = $new_issues['origin_issues'];

        $sprint->fill($updValues)->save();

        foreach ($new_issues['issues'] as $issue_no)
        {
            $this->pushSprint($project_key, $issue_no, $no);
        }

        $isSendMsg = $request->input('isSendMsg') && true;
        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new SprintEvent($project_key, $user, [ 'event_key' => 'start_sprint', 'isSendMsg' => $isSendMsg, 'data' => [ 'sprint_no' => $no ] ]));

        return Response()->json([ 'ecode' => 0, 'data' => $this->getValidSprintList($project_key) ]);
    }

    /**
     * publish the sprint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function complete(Request $request, $project_key, $no)
    {
        if (!$no)
        {
            throw new \UnexpectedValueException('the completed sprint cannot be empty', -11711);
        }
        $no = intval($no);

        $sprint = Sprint::where('project_key', $project_key)->where('no', $no)->first();
        if (!$sprint || $project_key != $sprint->project_key)
        {
            throw new \UnexpectedValueException('the sprint does not exist or is not in the project.', -11708);
        }

        if (!isset($sprint->status) || $sprint->status != 'active')
        {
            throw new \UnexpectedValueException('the completed sprint must be active.', -11712);
        }

        $completed_issues = $request->input('completed_issues') ?: [];
        if (array_diff($completed_issues, $sprint->issues))
        {
            throw new \UnexpectedValueException('the completed sprint issues have errors.', -11713);
        }

        $incompleted_issues = array_values(array_diff($sprint->issues, $completed_issues));

        $valid_incompleted_issues = DB::collection('issue_' . $project_key)->whereIn('no', $incompleted_issues)->where('del_flg', '<>', 1)->get([ 'no' ]);
        if ($valid_incompleted_issues)
        {
    	    $valid_incompleted_issues = array_column($valid_incompleted_issues, 'no');
    	    $incompleted_issues = array_values(array_intersect($incompleted_issues, $valid_incompleted_issues));
        }

        $updValues = [ 
            'status' => 'completed', 
            'real_complete_time' => time(), 
            'completed_issues' => $completed_issues,
            'incompleted_issues' => $incompleted_issues ];

        $sprint->fill($updValues)->save();

        if ($incompleted_issues)
        {
            $next_sprint = Sprint::where('project_key', $project_key)->where('status', 'waiting')->orderBy('no', 'asc')->first();
            if ($next_sprint)
            {
                $issues = !isset($next_sprint->issues) || !$next_sprint->issues ? [] : $next_sprint->issues;
                $issues = array_merge($incompleted_issues, $issues);
                $next_sprint->fill([ 'issues' => $issues ])->save();
            }
        }

        $isSendMsg = $request->input('isSendMsg') && true;
        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new SprintEvent($project_key, $user, [ 'event_key' => 'complete_sprint', 'isSendMsg' => $isSendMsg, 'data' => [ 'sprint_no' => $no ] ]));

        return Response()->json([ 'ecode' => 0, 'data' => $this->getValidSprintList($project_key) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $no)
    {
        if (!$no)
        {
            throw new \UnexpectedValueException('the deleted sprint cannot be empty', -11714);
        }
        $no = intval($no);

        $sprint = Sprint::where('project_key', $project_key)->where('no', $no)->first();
        if (!$sprint || $project_key != $sprint->project_key)
        {
            throw new \UnexpectedValueException('the sprint does not exist or is not in the project.', -11708);
        }

        if ($sprint->status == 'completed' || $sprint->status == 'active')
	{
            throw new \UnexpectedValueException('the active or completed sprint cannot be removed.', -11715);
        }

        if (isset($sprint->issues) && $sprint->issues)
        {
            $next_sprint = Sprint::where('project_key', $project_key)->where('no', '>', $no)->first();
            if ($next_sprint)
            {
                $issues = !isset($next_sprint->issues) || !$next_sprint->issues ? [] : $next_sprint->issues;
                $issues = array_merge($sprint->issues, $issues);
                $next_sprint->fill([ 'issues' => $issues ])->save();
            }
        }

        $sprint->delete();

        Sprint::where('project_key', $project_key)->where('no', '>', $no)->decrement('no');

        return Response()->json([ 'ecode' => 0, 'data' => $this->getValidSprintList($project_key) ]);
    }

    /**
     * get waiting or active sprint list.
     *
     * @param  string  $project_key
     * @return array 
     */
    public function getValidSprintList($project_key)
    {
        $sprints = Sprint::where('project_key', $project_key)
            ->whereIn('status', [ 'active', 'waiting' ])
            ->orderBy('no', 'asc')
            ->get();
        return $sprints;
    }

    /**
     * push sprint to issue detail.
     *
     * @param  string  $project_key
     * @param  string  $issue_no
     * @param  string  $sprint_no
     * @return void 
     */
    public function pushSprint($project_key, $issue_no, $sprint_no)
    {
        $issue = DB::collection('issue_' . $project_key)->where('no', $issue_no)->first();
        if (!$issue)
        {
            return;
        }

        $sprints = [];
        if (isset($issue['sprints']) && $issue['sprints'])
        {
            $sprints = $issue['sprints'];
        }
        array_push($sprints, $sprint_no);

        DB::collection('issue_' . $project_key)->where('no', $issue_no)->update(['sprints' => $sprints]);
    }

    /**
     * pop sprint from issue detail.
     *
     * @param  string  $project_key
     * @param  string  $issue_no
     * @param  string  $sprint_no
     * @return void 
     */
    public function popSprint($project_key, $issue_no, $sprint_no)
    {
        $issue = DB::collection('issue_' . $project_key)->where('no', $issue_no)->first();
        if (!$issue)
        {
            return;
        }

        $sprints = [];
        if (isset($issue['sprints']) && $issue['sprints'])
        {
            $sprints = $issue['sprints'];
        }
        $new_sprints = array_diff($sprints, [ $sprint_no ]);

        DB::collection('issue_' . $project_key)->where('no', $issue_no)->update(['sprints' => $new_sprints]);
    }

   /**
     * get the last column state of the kanban.
     *
     * @param  string $kanban_id
     * @return array 
     */
    public function getLastColumnStates($kanban_id)
    {
        // remaining start
        $last_column_states = [];
        $board = Board::find($kanban_id);
        if ($board && isset($board->columns))
        {
            $board_columns = $board->columns;
            $last_column = array_pop($board_columns) ?: [];
            if ($last_column && isset($last_column['states']) && $last_column['states'])
            {
                $last_column_states = $last_column['states'];
            }
        }
        return $last_column_states; 
    }

   /**
     * get sprint original state.
     *
     * @param  string $project_key
     * @param  array  $issue_nos
     * @param  string $kanban_id
     * @return array
     */
    public function filterIssues($project_key, $issue_nos, $kanban_id)
    {
        // get the kanban last column states
        $last_column_states = $this->getLastColumnStates($kanban_id);

        $new_issue_nos = [];
        $origin_issues = [];

        $issues = DB::collection('issue_' . $project_key)
            ->where([ 'no' => [ '$in' => $issue_nos ] ])
            ->where([ 'state' => [ '$nin' => $last_column_states ] ])
            ->get();
        foreach ($issues as $issue)
        {
            $new_issue_nos[] = $issue['no'];

            $origin_issues[] = [
                'no' => $issue['no'],
                'state' => isset($issue['state']) ? $issue['state'] : '',
                'story_points' => isset($issue['story_points']) ? $issue['story_points'] : 0 ];
        }

        return [ 'issues' => $new_issue_nos, 'origin_issues' => $origin_issues ];
    }

   /**
     * get sprint original state.
     *
     * @param  string $project_key 
     * @param  array  $issue_nos
     * @return array 
     */
    public function getOriginIssues($project_key, $issue_nos)
    {
        $origin_issues = [];
        $issues = DB::collection('issue_' . $project_key)->where([ 'no' => [ '$in' => $issue_nos ] ])->get();
        foreach ($issues as $issue)
        {
            $origin_issues[] = [ 
                'no' => $issue['no'], 
                'state' => isset($issue['state']) ? $issue['state'] : '', 
                'story_points' => isset($issue['story_points']) ? $issue['story_points'] : 0 ];
        }
        return $origin_issues;
    }

    /**
     * get sprint log.
     *
     * @param  string  $project_key
     * @param  string  $sprint_no
     * @return \Illuminate\Http\Response
     */
    public function getLog(Request $request, $project_key, $sprint_no)
    {
        $sprint_no = intval($sprint_no);

        $kanban_id = $request->input('kanban_id');
        if (!$kanban_id)
        {
            throw new \UnexpectedValueException('the kanban id cannot be empty', -11717);
        }

        $sprint = Sprint::where('project_key', $project_key)
            ->where('no', $sprint_no)
            ->first();
        if (!$sprint)
        {
            throw new \UnexpectedValueException('the sprint does not exist or is not in the project.', -11708);
        }

        $origin_issue_count = 0;
        if (isset($sprint->origin_issues))
        {
            $origin_issue_count = count($sprint->origin_issues); 
        }
        $origin_story_points = 0;
        if (isset($sprint->origin_issues))
        {
            foreach ($sprint->origin_issues as $val)
            {
                $origin_story_points += $val['story_points']; 
            }
        }

        $workingDays = $this->getWorkingDay($sprint->start_time, $sprint->complete_time); 
        $workingDayNum = 0;
        foreach ($workingDays as $val)
        {
            $workingDayNum += $val;
        }

        // guideline start
        $issue_count_guideline = [];
        $story_points_guideline = [];
        $issue_count_guideline[] = [ 'day' => '', 'value' => $origin_issue_count ];
        $story_points_guideline[] = [ 'day' => '', 'value' => $origin_story_points ];
        $tmp_issue_count = $origin_issue_count;
        $tmp_story_points = $origin_story_points;
        foreach ($workingDays as $day => $flag)
        {
            if ($flag === 1)
            {
                $tmp_issue_count = max([ round($tmp_issue_count - $origin_issue_count / $workingDayNum, 2), 0 ]);
                $tmp_story_points = max([ round($tmp_story_points - $origin_story_points / $workingDayNum, 2), 0 ]);
            }
            $issue_count_guideline[] = [ 'day' => substr($day, 5), 'value' => $tmp_issue_count, 'notWorking' => ($flag + 1) % 2 ];
            $story_points_guideline[] = [ 'day' => substr($day, 5), 'value' => $tmp_story_points, 'notWorking' => ($flag + 1) % 2 ];
        }
        // guideline end 

        // remaining start
        $last_column_states = $this->getLastColumnStates($kanban_id);

        $sprint_day_log = SprintDayLog::where('project_key', $project_key)
            ->where('no', $sprint_no)
            ->orderBy('day', 'asc')
            ->get();

        $issue_count_remaining = [];
        $story_points_remaining = [];
	$issue_count_remaining[] = [ 'day' => '', 'value' => $origin_issue_count ];
	$story_points_remaining[] = [ 'day' => '', 'value' => $origin_story_points ];
        foreach($sprint_day_log as $daylog)
        {
            $incompleted_issue_num = 0;
            $incompleted_story_points = 0;
            $issues = isset($daylog->issues) ? $daylog->issues : []; 
            foreach ($issues as $issue)
            {
                if (!in_array($issue['state'], $last_column_states))
                {
                    $incompleted_issue_num++;
                    $incompleted_story_points += isset($issue['story_points']) ? $issue['story_points'] : 0;
                } 
            }
            $issue_count_remaining[] = [ 'day' => substr($daylog->day, 5), 'value' => $incompleted_issue_num, 'notWorking' => isset($workingDays[$daylog->day]) ? ($workingDays[$daylog->day] + 1) % 2 : 0 ]; 
            $story_points_remaining[] = [ 'day' => substr($daylog->day, 5), 'value' => $incompleted_story_points, 'notWorking' => isset($workingDays[$daylog->day]) ? ($workingDays[$daylog->day] + 1) % 2 : 0 ]; 
        }
        // remaining start

        return Response()->json([ 'ecode' => 0, 'data' => [ 'issue_count' => [ 'guideline' => $issue_count_guideline, 'remaining' => $issue_count_remaining ], 'story_points' => [ 'guideline' => $story_points_guideline, 'remaining' => $story_points_remaining ] ] ]);
    }

    /**
     * get working day flag.
     *
     * @param  int  $start_time
     * @param  int  $complete_time
     * @return \Illuminate\Http\Response
     */
    public function getWorkingDay($start_time, $end_time)
    {
        $days = [];
        $tmp_time = $start_time;
        while ($tmp_time < $end_time)
        {
            $day = date('Y/m/d', $tmp_time);
            $days[] = $day;

            $week_flg = intval(date('w', $tmp_time));
            $workingDays[$day] = ($week_flg === 0 || $week_flg === 6) ? 0 : 1;

            $tmp_time += 24 * 60 * 60;
        }

        $singulars = CalendarSingular::where([ 'date' => [ '$in' => $days ] ])->get();
        foreach ($singulars as $singular)
        {
            $tmp = $singular->date;
            $workingDays[$tmp] = $singular->type == 'holiday' ? 0 : 1;
        }
        return $workingDays;
    }

    public function t2m($val, $options) 
    {
        $w2d = isset($options['w2d']) ? $options['w2d'] : 5;
        $d2h = isset($options['d2h']) ? $options['d2h'] : 8;

        $total = 0;
        $tmp = explode(' ', $val);
        foreach ($tmp as $v)
        {
            $t = substr($v, 0, strlen($v) - 1);
            $u = substr($v, -1);

            if ($u == 'w' || $u == 'W')
            {
                $total += $total + $t * $w2d * $d2h;
            }
            else if ($u == 'd' || $u == 'D')
            {
                $total += $t * $d2h * 60;
            }
            else if ($u == 'h' || $u == 'H')
            {
                $total += $t * 60;
            }
            else 
            {
                $total += $t;
            }
        }
        return $total;
    }

    public function getRemainingTime($issue_no)
    {
        $origin_estimate_time = 0;
        $issue = DB::where('no', $issue_no)->first();
        if (isset($issue['original_estimate']))
        {
            $origin_estimate_time = $issue['original_estimate'];
        }

        $leave_estimate_m = $this->t2m($origin_estimate_time);
        if ($leave_estimate_m <= 0)
        {
            return 0;
        }

        $worklogs = Worklog::Where('project_key', $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        foreach ($worklogs as $worklog)
        {
            if (!isset($worklog['spend']))
            {
                continue;
            }
            if (isset($worklog['adjust_type']))
            {
                if ($worklog['adjust_type'] == 1)
                {
                    $spend_m = $this->t2m($worklog['spend']);
                    $leave_estimate_m = $leave_estimate_m - $spend_m;
                }
                else if ($worklog['adjust_type'] == 3)
                {
                    $leave_estimate_m = $this->t2m(isset($worklog['cut']) ? $worklog['cut'] : 0);
                }
            }
        }
    }
}
