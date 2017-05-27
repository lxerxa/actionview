<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\Workflow\Eloquent\Definition;
use App\Project\Provider;
use DB;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $states = Provider::getStateList($project_key);
        foreach ($states as $key => $state)
        {
            $workflows = Definition::whereRaw([ 'state_ids' => isset($state['key']) ? $state['key'] : $state['_id'] ])
                             ->orderBy('project_key', 'asc')
                             ->get([ 'project_key', 'name' ])
                             ->toArray();
            $states[$key]['workflows'] = $workflows;

            if ($workflows)
            {
                $states[$key]['is_used'] = true;
            }
            else
            {
                $states[$key]['is_used'] = $this->isFieldUsedByIssue($project_key, 'state', $state); 
            }

            $states[$key]['workflows'] = array_filter($workflows, function($item) use($project_key) { 
                                             return $item['project_key'] === $project_key || $item['project_key'] === '$_sys_$';
                                         });
        }
        return Response()->json(['ecode' => 0, 'data' => $states]);
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

        if (Provider::isStateExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('state name cannot be repeated', -10002);
        }

        $state = State::create([ 'project_key' => $project_key ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $state]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $state = State::find($id);
        //if (!$state || $project_key != $state->project_key)
        //{
        //    throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $state]);
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
        $state = State::find($id);
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
            if ($state->name !== $name && Provider::isStateExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('state name cannot be repeated', -10002);
            }
        }

        $state->fill($request->except(['project_key']))->save();
        return Response()->json(['ecode' => 0, 'data' => State::find($id)]);
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
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'state', $state->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used by issue.', -10002);
        }

        $isUsed = Definition::whereRaw([ 'state_ids' => isset($state->key) ? $state->key : $id ])->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used by workflow.', -10002);
        }

        State::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
