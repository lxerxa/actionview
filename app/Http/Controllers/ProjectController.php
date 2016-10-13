<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Project;
use App\Acl\Acl;
use App\Project\Provider;

use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;
use Sentinel;

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
        foreach ($projects as $key => $project)
        {
            $projects[$key]->principal = Sentinel::findById($project->principal);
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

        $principal = $request->input('principal');
        if (!$principal)
        {
            throw new \InvalidArgumentException('the principal must be appointed.', -10002);
        }
        if (!Sentinel::findById($principal))
        {
            throw new \InvalidArgumentException('the user is not exists.', -10002);
        }
        // fix me check if user is available
        // save the project
        $project = Project::create($request->all()); //fix me
        // trigger add user to usrproject
        Event::fire(new AddUserToRoleEvent([ $principal ], $key));

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
        $permissions = Acl::getPermissions('mm', $project->key); // fix me
        // get project users
        $users = Provider::getUserList($project->key);
        // get state list
        $states = Provider::getStateList($project->key, ['name']);
        // get resolution list
        $resolutions = Provider::getResolutionList($project->key, ['name']);
        // get priority list
        $priorities = Provider::getPriorityList($project->key, ['color', 'name']);
        // get version list
        $versions = Provider::getVersionList($project->key, ['name']);
        // get module list
        $modules = Provider::getModuleList($project->key, ['name']);
        // get project types
        $types = Provider::getTypeListExt($project->key, [ 'assignee' => $users, 'state' => $states, 'resolution' => $resolutions, 'priority' => $priorities, 'version' => $versions, 'module' => $modules ]);

        return Response()->json([ 'ecode' => 0, 'data' => $project, 'options' => [ 'permissions' => $permissions, 'users' => $users, 'config' => [ 'types' => $types, 'states' => $states, 'resolutions' => $resolutions, 'priorities' => $priorities ] ] ]);
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
        // check is user is available
        $principal = $request->input('principal');
        if (isset($principal))
        {
            if (!$principal)
            {
                throw new \InvalidArgumentException('the principal must be appointed.', -10002);
            }
            if (!Sentinel::findById($principal))
            {
                throw new \InvalidArgumentException('the user is not exists.', -10002);
            }
        }

        $project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -10002);
        }
        $project->fill($request->except(['key']))->save();

        if (isset($principal))
        {
            if ($project->principal != $principal)
            {
                Event::fire(new AddUserToRoleEvent([ $principal ], $project->key));
                Event::fire(new DelUserFromRoleEvent([ $project->principal ], $project->key));
            }
        }

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
