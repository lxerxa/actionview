<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Worklog;
use App\Project\Provider;

class WorklogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key, $issue_id)
    {
        $worklogs = Provider::getWorklogList($project_key);
        return Response()->json(['ecode' => 0, 'data' => $worklogs]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key, $issue_id)
    {
        $values = [];

        $spend = $request->input('spend');
        if (!$spend || trim($spend) == '')
        {
            throw new \UnexpectedValueException('the spend-time can not be empty.', -10002);
        }

        if (!$this->ttCheck($spend))
        {
            throw new \UnexpectedValueException('the format of spend-time is incorrect.', -10002);
        }
        $values['spend'] = $this->ttHandle($spend);

        $stated_at = $request->input('stated_at');
        if (!$started_at)
        {
            throw new \UnexpectedValueException('the start time can not be empty.', -10002);
        }
        $values['stated_at'] = $started_at;

        $adjust_type = $request->input('adjust_type');
        if (!$adjust_type)
        {
            $adjust_type = '1';
        }
        $values['adjust_type'] = $adjust_type;

        if ($adjust_type == '3')
        {
            $leave_estimate = $request->input('leave_estimate');
            if (!$leave_estimate || trim($leave_estimate) == '')
            {
                throw new \UnexpectedValueException('the leave-estimate-time can not be empty.', -10002);
            }

            if (!$this->ttCheck($leave_estimate))
            {
                throw new \UnexpectedValueException('the format of leave-estimate-time is incorrect.', -10002);
            }
            $values['leave_estimate'] = $this->ttHandle($leave_estimate);
        }

        $isIssueExisted = Provider::isIssueExisted(project_key, $issue_id);
        if (!$isIssueExisted) {
            throw new \UnexpectedValueException('the issue is not existed.', -10002);
        }

        $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];

        $worklog = Worklog::create([ 'project_key' => $project_key, 'issue_id' => $issue_id ] + $values);
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
            throw new \UnexpectedValueException('the worklog does not exist or is not in the issue or is not in the project.', -10002);
        }

        $values = [];

        $spend = $request->input('spend');
        if (!$spend || trim($spend) == '')
        {
            throw new \UnexpectedValueException('the spend-time can not be empty.', -10002);
        }

        if (!$this->ttCheck($spend))
        {
            throw new \UnexpectedValueException('the format of spend-time is incorrect.', -10002);
        }
        $values['spend'] = $this->ttHandle($spend);

        $stated_at = $request->input('stated_at');
        if (!$started_at)
        {
            throw new \UnexpectedValueException('the start time can not be empty.', -10002);
        }
        $values['stated_at'] = $started_at;

        $adjust_type = $request->input('adjust_type');
        if (!$adjust_type)
        {
            $adjust_type = '1';
        }
        $values['adjust_type'] = $adjust_type;

        if ($adjust_type == '3')
        {
            $leave_estimate = $request->input('leave_estimate');
            if (!$leave_estimate || trim($leave_estimate) == '')
            {
                throw new \UnexpectedValueException('the leave-estimate-time can not be empty.', -10002);
            }

            if (!$this->ttCheck($leave_estimate))
            {
                throw new \UnexpectedValueException('the format of leave-estimate-time is incorrect.', -10002);
            }
            $values['leave_estimate'] = $this->ttHandle($leave_estimate);
        }

        $worklog->fill($values)->save();
        return Response()->json(['ecode' => 0, 'data' => Worklog::find($id)]);
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
            throw new \UnexpectedValueException('the worklog does not exist or is not in the issue or is not in the project.', -10002);
        }

        Worklog::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
