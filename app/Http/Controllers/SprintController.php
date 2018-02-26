<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Sprint;
use App\Project\Provider;
use DB;

class SprintController extends Controller
{
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
        $sprint = Sprint::create([ 'project_key' => $project_key, no: $sprint_count + 1 ]);
        return Response()->json(['ecode' => 0, 'data' => $sprint]);
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $no)
    {
        $sprint = Sprint::where('project_key', $project_key)->where('no', $no)->first();
        if (!$sprint || $project_key != $sprint->project_key)
        {
            throw new \UnexpectedValueException('the sprint does not exist or is not in the project.', -12402);
        }

        $updValues = [];
        $status = $request->input('status');
        if (isset($status))
        {
            $updValues['status'] = $status;
        }

        $start_time = $request->input('start_time');
        if (isset($start_time))
        {
            $updValues['start_time'] = $start_time;
        }

        $end_time = $request->input('end_time');
        if (isset($end_time))
        {
            $updValues['end_time'] = $end_time;
        }

        $sprint->fill($updValues)->save();

        $sprint = Sprint::where('project_key', $project_key)->where('no', $no)->first();
        return Response()->json([ 'ecode' => 0, 'data' => sprint ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $state = State::find($id);
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -12402);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'state', $state->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used in issue.', -12403);
        }

        $isUsed = Definition::whereRaw([ 'state_ids' => isset($state->key) ? $state->key : $id ])->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used in workflow.', -12404);
        }

        State::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
