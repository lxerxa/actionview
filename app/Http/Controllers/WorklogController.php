<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Worklog;
use App\Project\Provider;

class WorklogController extends Controller
{

    use TimeTrackTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key, $issue_id)
    {
        $sort = ($request->input('sort') === 'desc') ? 'desc' : 'asc';

        $worklogs = Worklog::Where('project_key', $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('recorded_at', $sort)
            ->get();
        return Response()->json(['ecode' => 0, 'data' => $worklogs, 'options' => [ 'current_time' => time() ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key, $issue_id)
    {
        if (!$this->isPermissionAllowed($project_key, 'add_worklog')) {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $values = [];

        $spend = $request->input('spend');
        if (!$spend)
        {
            throw new \UnexpectedValueException('the spend-time can not be empty.', -11300);
        }
        if (!$this->ttCheck($spend))
        {
            throw new \UnexpectedValueException('the format of spend-time is incorrect.', -11301);
        }
        $values['spend'] = $this->ttHandle($spend);
        $values['spend_m'] = $this->ttHandleInM($spend);

        $started_at = $request->input('started_at');
        if (!$started_at)
        {
            throw new \UnexpectedValueException('the start time can not be empty.', -11302);
        }
        $values['started_at'] = $started_at;

        $adjust_type = $request->input('adjust_type');
        if (!in_array($adjust_type, ['1', '2', '3', '4']))
        {
            throw new \UnexpectedValueException('the adjust-type value is incorrect.', -11303);
        }
        $values['adjust_type'] = $adjust_type;

        if ($adjust_type == '3')
        {
            $leave_estimate = $request->input('leave_estimate');
            if (!$leave_estimate)
            {
                throw new \UnexpectedValueException('the leave-estimate-time can not be empty.', -11304);
            }

            if (!$this->ttCheck($leave_estimate))
            {
                throw new \UnexpectedValueException('the format of leave-estimate-time is incorrect.', -11305);
            }
            $values['leave_estimate'] = $this->ttHandle($leave_estimate);
            $values['leave_estimate_m'] = $this->ttHandleInM($values['leave_estimate']);
        }

        if ($adjust_type == '4')
        {
            $cut = $request->input('cut');
            if (!$cut)
            {
                throw new \UnexpectedValueException('the cut-time can not be empty.', -11306);
            }

            if (!$this->ttCheck($cut))
            {
                throw new \UnexpectedValueException('the format of cut-time is incorrect.', -11307);
            }
            $values['cut'] = $this->ttHandle($cut);
            $values['cut_m'] = $this->ttHandleInM($values['cut']);
        }

        $comments = $request->input('comments');
        $values['comments'] = $comments ?: '';

        $isIssueExisted = Provider::isIssueExisted($project_key, $issue_id);
        if (!$isIssueExisted) {
            throw new \UnexpectedValueException('the issue is not existed.', -11308);
        }

        $recorder = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $worklog = Worklog::create([ 'project_key' => $project_key, 'issue_id' => $issue_id, 'recorder' => $recorder, 'recorded_at' => time() ] + $values);

        // trigger event of issue worklog added
        Event::fire(new IssueEvent($project_key, $issue_id, $recorder, [ 'event_key' => 'add_worklog', 'data' => $values ]));

        return Response()->json(['ecode' => 0, 'data' => $worklog]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $worklog = Worklog::find($id);
        //if (!$worklog || $project_key != $worklog->project_key)
        //{
        //    throw new \UnexpectedValueException('the worklog does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $worklog]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $issue_id, $id)
    {
        $worklog = Worklog::find($id);
        if (!$worklog || $project_key != $worklog->project_key || $issue_id != $worklog->issue_id)
        {
            throw new \UnexpectedValueException('the worklog does not exist or is not in the issue or is not in the project.', -11309);
        }

        if (!$this->isPermissionAllowed($project_key, 'edit_worklog') && !($worklog->recorder['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'edit_self_worklog'))) 
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $values = [];
        $spend = $request->input('spend');
        if (isset($spend))
        {
            if (!$spend)
            {
                throw new \UnexpectedValueException('the spend-time can not be empty.', -11300);
            }
            if (!$this->ttCheck($spend))
            {
                throw new \UnexpectedValueException('the format of spend-time is incorrect.', -11301);
            }
            $values['spend'] = $this->ttHandle($spend);
            $values['spend_m'] = $this->ttHandleInM($spend);
        }

        $started_at = $request->input('started_at');
        if (isset($started_at))
        {
            if (!$started_at)
            {
                throw new \UnexpectedValueException('the start time can not be empty.', -11302);
            }
            $values['started_at'] = $started_at;
        }

        $adjust_type = $request->input('adjust_type');
        if (isset($adjust_type))
        {
            if (!in_array($adjust_type, ['1', '2', '3', '4']))
            {
                throw new \UnexpectedValueException('the adjust-type value is incorrect.', -11303);
            }

            $values['adjust_type'] = $adjust_type;
            if ($adjust_type == '3')
            {
                $leave_estimate = $request->input('leave_estimate');
                if (!$leave_estimate)
                {
                    throw new \UnexpectedValueException('the leave-estimate-time can not be empty.', -11304);
                }
                if (!$this->ttCheck($leave_estimate))
                {
                    throw new \UnexpectedValueException('the format of leave-estimate-time is incorrect.', -11305);
                }
                $values['leave_estimate'] = $this->ttHandle($leave_estimate);
                $values['leave_estimate_m'] = $this->ttHandleInM($values['leave_estimate']);
            } 
            else if ($adjust_type == '4')
            {
                $cut = $request->input('cut');
                if (!$cut)
                {
                    throw new \UnexpectedValueException('the cut-time can not be empty.', -11306);
                }

                if (!$this->ttCheck($cut))
                {
                    throw new \UnexpectedValueException('the format of cut-time is incorrect.', -11307);
                }
                $values['cut'] = $this->ttHandle($cut);
                $values['cut_m'] = $this->ttHandleInM($values['cut']);
            }
        }

        $comments = $request->input('comments');
        if (isset($comments)) 
        {
            $values['comments'] = $comments ?: '';
        }
        $worklog->fill([ 'edited_flag' => 1 ] + array_except($values, [ 'recorder', 'recorded_at' ]))->save();

        // trigger event of worklog edited 
        $worklog = Worklog::find($id);
        $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new IssueEvent($project_key, $issue_id, $cur_user, [ 'event_key' => 'edit_worklog', 'data' => $worklog->toArray() ]));

        return Response()->json(['ecode' => 0, 'data' => $worklog]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $issue_id, $id)
    {
        $worklog = Worklog::find($id);
        if (!$worklog || $project_key != $worklog->project_key || $issue_id != $worklog->issue_id)
        {
            throw new \UnexpectedValueException('the worklog does not exist or is not in the issue or is not in the project.', -11309);
        }

        if (!$this->isPermissionAllowed($project_key, 'delete_worklog') && !($worklog->recorder['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'delete_self_worklog'))) 
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        Worklog::destroy($id);

        // trigger event of worklog deleted 
        $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new IssueEvent($project_key, $issue_id, $cur_user, [ 'event_key' => 'del_worklog', 'data' => $worklog->toArray() ]));

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
