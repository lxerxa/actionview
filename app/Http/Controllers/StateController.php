<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\Project\Project;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $states = State::where([ 'project_key' => $project_key ])->orderBy('created_at', 'asc')->get([ 'name', 'description' ]);
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
        $state = State::create($request->all() + [ 'project_key' => $project_key ]);
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
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
        }
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
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }
        $state = State::find($id);
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
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

        State::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
