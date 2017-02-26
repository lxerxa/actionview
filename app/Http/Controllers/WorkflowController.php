<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Workflow\Eloquent\Definition;
use App\Workflow\Workflow;
use App\Project\Provider;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $workflows = Provider::getWorkflowList($project_key, [ 'name', 'category', 'description', 'latest_modified_time', 'latest_modifier', 'steps' ]);

        return Response()->json([ 'ecode' => 0, 'data' => $workflows ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        $contents = $request->input('contents');
        if (isset($contents) && $contents)
        {
            $latest_modifier = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];
            $latest_modified_time = date('Y-m-d H:i:s');
            $screen_ids = Workflow::getScreens($contents);
            $steps = Workflow::getStepNum($contents);
        }
        else
        {
            $latest_modifier = []; $latest_modified_time = ''; $screen_ids = []; $steps = 0;
        }

        $source_id = $request->input('source_id');
        if (isset($source_id) && $source_id)
        {
            $source_definition = Definition::find($source_id);
            $latest_modifier = $source_definition->latest_modifier;
            $latest_modified_time = $source_definition->latest_modified_time;
            $screen_ids = $source_definition->screen_ids;
            $steps = $source_definition->steps;
            $contents = $source_definition->contents;
        }

        $workflow = Definition::create([ 'project_key' => $project_key, 'latest_modifier' => $latest_modifier, 'latest_modified_time' => $latest_modified_time, 'screen_ids' => $screen_ids, 'steps' => $steps, 'contents' => $contents ] + $request->all());
        return Response()->json([ 'ecode' => 0, 'data' => $workflow ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $project_key, $id)
    {
        $workflow = Definition::find($id);
        //if (!$workflow || $project_key != $workflow->project_key)
        //{
        //    throw new \UnexpectedValueException('the workflow does not exist or is not in the project.', -10002);
        //}

        if ($request->input('flag') == 's')
        {
            return Response()->json([ 'ecode' => 0, 'data' => $workflow ]);
        } 
        else 
        {
            $states = Provider::getStateList($project_key, ['name']);
            $screens = Provider::getScreenList($project_key, ['name']);
            $resolutions = Provider::getResolutionList($project_key, ['name']);
            $roles = Provider::getRoleList($project_key, ['name']);
            $events = Provider::getEventList($project_key, ['name']);
            $users = Provider::getUserList($project_key);

            return Response()->json([ 'ecode' => 0, 'data' => $workflow, 'options' => [ 'states' => $states, 'screens' => $screens, 'resolutions' => $resolutions, 'events' => $events, 'roles' => $roles, 'users' => $users ] ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }
        $workflow = Definition::find($id);
        if (!$workflow || $project_key != $workflow->project_key)
        {
            throw new \UnexpectedValueException('the workflow does not exist or is not in the project.', -10002);
        }

        $contents = $request->input('contents');
        if (isset($contents))
        {
            $latest_modifier = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];
            $latest_modified_time = date('Y-m-d H:i:s');

            $workflow->latest_modifier = $latest_modifier;
            $workflow->latest_modified_time = $latest_modified_time;
            $workflow->screen_ids = Workflow::getScreens($contents);
            $workflow->steps = Workflow::getStepNum($contents);
        }

        $workflow->fill($request->except([ 'project_key' ]))->save();
        return Response()->json([ 'ecode' => 0, 'data' => Definition::find($id) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $workflow = Definition::find($id);
        if (!$workflow || $project_key != $workflow->project_key)
        {
            throw new \UnexpectedValueException('the workflow does not exist or is not in the project.', -10002);
        }

        Definition::destroy($id);
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
