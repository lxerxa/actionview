<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\SprintDayLog;
use App\Project\Eloquent\Board;
use App\Project\Provider;
use App\Customization\Eloquent\CalendarSingular;
use DB;

class SprintController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:manage_project', [ 'except' => [ 'getLog' ] ]);
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
    public function show($project_key, $id)
    {
        $sprint = Sprint::find($id);
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

        $active_sprint_exists = Sprint::where('project_key', $project_key)->where('status', 'active')->exists();
        if ($active_sprint_exists)
        {
            throw new \UnexpectedValueException('the active sprint has been exists.', -11706);
        }

        $before_waiting_sprint_exists = Sprint::where('project_key', $project_key)->where('status', 'waiting')->where('no', '<', $no)->exists();
        if ($before_waiting_sprint_exists)
        {
            throw new \UnexpectedValueException('the more first sprint has been exists.', -11707);
        }

        $sprint = Sprint::where('project_key', $project_key)->where('no', $no)->first();
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

        $updValues['origin_issues'] = [];
        if (isset($sprint->issues))
        {
            $updValues['origin_issues'] = $sprint->issues;
        }

        $sprint->fill($updValues)->save();

        if (isset($sprint->issues) && $sprint->issues)
        {
            foreach ($sprint->issues as $issue_no)
            {
                $this->pushSprint($project_key, $issue_no, $no);
            }
        }

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

        $incompleted_issues = array_diff($sprint->issues, $completed_issues);

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
     * get sprint log.
     *
     * @param  string  $project_key
     * @param  string  $sprint_no
     * @return \Illuminate\Http\Response
     */
    public function getLog(Request $request, $project_key, $sprint_no)
    {
        //$kanban_id = $request->input('kanban_id');
        //if (!$kanban_id)
        //{
        //    throw new \UnexpectedValueException('the deleted sprint cannot be empty', -11714);
        //}
        $kanban_id = '5aa5e7831d41c8561532141c';

        $sprint = Sprint::where('project_key', $project_key)
            ->where('no', intval($sprint_no))
            ->first();
        //if (!$sprint)
        //{
        //    throw new \UnexpectedValueException('the deleted sprint cannot be empty', -11714);
        //}

        $origin_issue_count = 0;
        if (isset($sprint->origin_issues))
        {
            $origin_issue_count = count($sprint->origin_issues); 
        }

        $workingDays = $this->getWorkingDay($sprint->start_time, $sprint->complete_time); 
        $workingDayNum = 0;
        foreach ($workingDays as $val)
        {
            $workingDayNum += $val;
        }

        // issue count guideline start
        $issue_count_guideline = [];
        $issue_count_guideline[] = [ 'day' => '', 'value' => $origin_issue_count ];
        $tmp_issue_count = $origin_issue_count;
        foreach ($workingDays as $day => $flag)
        {
            if ($flag === 0)
            {
                $tmp_issue_count = $tmp_issue_count - round($origin_issue_count / $workingDayNum, 2);
            }
            $issue_count_guideline[] = [ 'day' => substr($day, 5), 'value' => $tmp_issue_count, 'notWorking' => ($flag + 1) % 2 ];
        }
        // issue count guideline end 

        // issue count remaining start
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

        $sprint_day_log = SprintDayLog::where('project_key', $project_key)
            ->where('no', $sprint_no)
            ->orderBy('day', 'desc')
            ->get();

        $issue_count_remaining = [];
	$issue_count_remaining[] = [ 'day' => '', 'value' => $origin_issue_count ];
        foreach($sprint_day_log as $daylog)
        {
            $incomplete_num = 0;
            $issue_state_map = isset($daylog->issue_state_map) ? $daylog->issue_state_map : []; 
            foreach ($issue_state_map as $state)
            {
                if (!in_array($state, $last_column_states))
                {
                    $incomplete_num++;
                } 
            }
            $issue_count_remaining[] = [ 'day' => substr($daylog->day, 5), 'value' => $incomplete_num, 'notWorking' => $workingDays[$daylog->day] ? ($workingDays[$daylog->day] + 1) % 2 : 0 ]; 
        }
        // issue count remaining start

        return Response()->json([ 'ecode' => 0, 'data' => [ 'issue_count' => [ 'guideline' => $issue_count_guideline ] ] ]);
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

        $singulars = CalendarSingular::where([ 'day' => [ '$in' => $days ] ])->get();
        foreach ($singulars as $singular)
        {
            $tmp = $singular->day;
            $workingDays[$tmp] = $singular->flag;
        }
        return $workingDays;
    }
}
