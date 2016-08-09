<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Project;
use App\Acl\Acl;

use Sentinel;
use Activation;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:global:manage_project', [ 'except' => [ 'index' ] ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // fix me
        $wheres = [];
        $projects = Project::whereRaw($wheres)->get();
        foreach ($projects as $project)
        {
            //var_dump($field->toArray());
        }
        return Response()->json([ 'ecode' => 0, 'data' => $projects ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        $key = $request->input('key');
        if (!$key || trim($key) == '')
        {
            throw new \InvalidArgumentException('project key cannot be empty.', -10002);
        }
        if (Project::Where('key', $key)->first())
        {
            throw new \InvalidArgumentException('project key has already existed.', -10002);
        }

        $project = Project::create($request->all() + [ 'creator_id' => 'liuxu' ]); //fix me
        return Response()->json([ 'ecode' => 0, 'data' => $project ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($key)
    {
        $project = Project::where('key', $key)->first();
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -10002);
        }
        // get action allow of the project.
        $actions = Acl::getPermissions('mm', $project->key); // fix me
        return Response()->json([ 'ecode' => 0, 'data' => $project, 'acl' => $actions ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }

        $project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -10002);
        }
        $project->fill($request->except(['key']))->save();

        return Response()->json([ 'ecode' => 0, 'data' => Project::find($id) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Project::destroy($id);
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
