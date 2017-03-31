<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\UserProject;
use App\Customization\Eloquent\Type;
use App\Acl\Acl;
use App\Project\Provider;

use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;
use Sentinel;
use DB;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:global:manage_project', [ 'except' => [ 'index' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function myproject(Request $request)
    {
        // fix me
        $user_projects = UserProject::whereRaw([ 'user_id' => $this->user->id ])->orderBy('latest_access_time', 'desc')->get(['project_key'])->toArray();

        $pkeys = array_column($user_projects, 'project_key');

        $offset_key = $request->input('offset_key');
        if (isset($offset_key))
        {
            $ind = array_search($offset_key, $pkeys);
            if ($ind === false)
            {
                $pkeys = [];
            }
            else
            {
                $pkeys = array_slice($pkeys, $ind + 1); 
            }
        }

        $limit = $request->input('limit');
        if (!isset($limit))
        {
            $limit = 30;
        }

        $status = $request->input('status');
        if (!isset($status))
        {
            $status = 'all';
        }

        $name = $request->input('name');
        if (isset($name) && $name)
        {
            $name = trim($name);
        }

        $projects = [];
        foreach ($pkeys as $pkey)
        {
            $query = Project::where('key', $pkey);
            if ($name)
            {
                $query = $query->where('name', 'like', '%' . $name . '%');
            }
            if ($status != 'all')
            {
                $query = $query->where('status', $status);
            }

            $project = $query->first();
            if (!$project) 
            {
                continue;
            }

            $projects[] = $project->toArray();
            if (count($projects) >= $limit)
            {
                break;
            }
        }
        
        foreach ($projects as $key => $project)
        {
            $projects[$key]['principal']['nameAndEmail'] = $project['principal']['name'] . '(' . $project['principal']['email'] . ')';
        }

        return Response()->json([ 'ecode' => 0, 'data' => $projects ]);
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
        $projects = Project::whereRaw($wheres)->get()->toArray();
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
        $insValues = [];

        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }
        $insValues['name'] = trim($name);

        $key = $request->input('key');
        if (!$key || trim($key) == '')
        {
            throw new \InvalidArgumentException('project key cannot be empty.', -10002);
        }
        if (Project::Where('key', $key)->exists())
        {
            throw new \InvalidArgumentException('project key has been taken.', -10002);
        }
        $insValues['key'] = trim($key);

        $principal = $request->input('principal');
        if (!isset($principal) || !$principal)
        {
            $insValues['principal'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        }
        else
        {
            $principal_info = Sentinel::findById($principal);
            if (!$principal_info)
            {
                throw new \InvalidArgumentException('the user is not exists.', -10002);
            }
            $insValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' => $principal_info->email ];
        }

        $description = $request->input('description');
        if (isset($description) && trim($description))
        {
            $insValues['description'] = trim($description);
        }

        $insValues['category'] = 1;
        $insValues['status'] = 'active';
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        // save the project
        $project = Project::create($insValues); //fix me
        // add issue-type template to project
        $this->initialize($project->key, $project->category);
        // trigger add user to usrproject
        Event::fire(new AddUserToRoleEvent([ $insValues['principal']['id'], $this->user->id ], $key));

        if (isset($project->principal))
        {
            $project->principal = array_merge($insValues['principal'], [ 'nameAndEmail' => $insValues['principal']['name'] . '(' . $insValues['principal']['email'] . ')' ]);
        }

        return Response()->json([ 'ecode' => 0, 'data' => $project ]);
    }

    /**
     * initialize project data.
     *
     * @param  string  $key
     * @param  int     $id
     * @return 
     */
    public function initialize($key, $category)
    {
        $types = Type::where('category', $category)->get()->toArray();
        foreach ($types as $type)
        {
            Type::create(array_only($type, [ 'name', 'abb', 'screen_id', 'workflow_id', 'sn', 'disabled', 'default' ]) + [ 'project_key' => $key ]);
        }
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
        // get searchers
        //$searchers = DB::collection('searcher_' . $key)->where('user', $this->user->id)->orderBy('created_at', 'asc')->get();
        // get project users
        //$users = Provider::getUserList($project->key);
        // get state list
        //$states = Provider::getStateList($project->key, ['name']);
        // get resolution list
        //$resolutions = Provider::getResolutionList($project->key, ['name']);
        // get priority list
        //$priorities = Provider::getPriorityList($project->key, ['color', 'name']);
        // get version list
        //$versions = Provider::getVersionList($project->key, ['name']);
        // get module list
        //$modules = Provider::getModuleList($project->key, ['name']);
        // get project types
        //$types = Provider::getTypeListExt($project->key, [ 'assignee' => $users, 'state' => $states, 'resolution' => $resolutions, 'priority' => $priorities, 'version' => $versions, 'module' => $modules ]);

        return Response()->json([ 'ecode' => 0, 'data' => $project, 'options' => parent::arrange([ 'permissions' => $permissions ]) ]);
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
        $updValues = [];
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
            $updValues['name'] = trim($name);
        }
        // check is user is available
        $principal = $request->input('principal');
        if (isset($principal))
        {
            if (!$principal)
            {
                throw new \InvalidArgumentException('the principal must be appointed.', -10002);
            }

            $principal_info = Sentinel::findById($principal);
            if (!$principal_info)
            {
                throw new \InvalidArgumentException('the user is not exists.', -10002);
            }
            $updValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' =>  $principal_info->email ]; 
        }

        $description = $request->input('description');
        if (isset($description) && trim($description))
        {
            $updValues['description'] = trim($description);
        }

        $status = $request->input('status');
        if (isset($status) && in_array($status, [ 'active', 'closed' ]))
        {
            $updValues['status'] = $status;
        }

        $project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -10002);
        }
        $project->fill($updValues)->save();

        if (isset($principal))
        {
            $old_principal = $project->principal;
            if ($old_principal['id'] != $principal)
            {
                Event::fire(new AddUserToRoleEvent([ $principal ], $project->key));
                Event::fire(new DelUserFromRoleEvent([ $old_principal['id'] ], $project->key));
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

    /**
     * check if project key has been taken 
     *
     * @param  string  $key
     * @return \Illuminate\Http\Response
     */
    public function checkKey($key)
    {
        $isExisted = Project::Where('key', $key)->exists(); 
        return Response()->json([ 'ecode' => 0, 'data' => [ 'flag' => $isExisted ? '2' : '1' ] ]);
    }
}
