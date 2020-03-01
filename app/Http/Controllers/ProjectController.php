<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\UserGroupProject;
use App\Project\Eloquent\AccessProjectLog;
use App\Customization\Eloquent\Type;
use App\Acl\Acl;
use App\Project\Provider;

use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;
use App\System\Eloquent\SysSetting;
use Sentinel;
use DB;

use MongoDB\BSON\ObjectID;
use MongoDB\Model\CollectionInfo;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin', [ 'only' => [ 'index', 'getOptions', 'updMultiStatus', 'createMultiIndex', 'destroy' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function recent(Request $request)
    {
        // get bound groups
        $group_ids = array_column(Acl::getBoundGroups($this->user->id), 'id');
        $user_projects = UserGroupProject::whereIn('ug_id', array_merge($group_ids, [ $this->user->id ]))
            ->where('link_count', '>', 0)
            ->get(['project_key'])
            ->toArray();
        $pkeys = array_column($user_projects, 'project_key');

        // get latest access projects
        $accessed_projects = AccessProjectLog::where('user_id', $this->user->id)
            ->orderBy('latest_access_time', 'desc')
            ->get(['project_key'])
            ->toArray();
        $accessed_pkeys = array_column($accessed_projects, 'project_key');

        $new_accessed_pkeys = array_unique(array_intersect($accessed_pkeys, $pkeys));

        $projects = [];
        foreach ($new_accessed_pkeys as $pkey)
        {
            $project = Project::where('key', $pkey)->first();
            if (!$project || $project->status === 'closed') 
            {
                continue;
            }

            $projects[] = [ 'key' => $project->key, 'name' => $project->name ];
            if (count($projects) >= 5) { break; }
        }

        return  Response()->json([ 'ecode' => 0, 'data' => $projects ]); 
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function myproject(Request $request)
    {
        // get bound groups
        $group_ids = array_column(Acl::getBoundGroups($this->user->id), 'id');
        // fix me
        $user_projects = UserGroupProject::whereIn('ug_id', array_merge($group_ids, [ $this->user->id ]))
            ->where('link_count', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get(['project_key'])
            ->toArray();

        $pkeys = array_values(array_unique(array_column($user_projects, 'project_key')));

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
            $limit = 36;
        }
        $limit = intval($limit);

        $status = $request->input('status');
        if (!isset($status))
        {
            $status = 'all';
        }

        $name = $request->input('name');

        $projects = [];
        foreach ($pkeys as $pkey)
        {
            $query = Project::where('key', $pkey);
            if ($name)
            {
                $query->where(function ($query) use ($name) {
                    $query->where('key', 'like', '%' . $name . '%')->orWhere('name', 'like', '%' . $name . '%');
                });
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

        $syssetting = SysSetting::first();
        $allow_create_project = isset($syssetting->properties['allow_create_project']) ? $syssetting->properties['allow_create_project'] : 0;

        return Response()->json([ 'ecode' => 0, 'data' => $projects, 'options' => [ 'limit' => $limit, 'allow_create_project' => $allow_create_project ] ]);
    }

    /**
     * get the options of project.
     *
     * @return \Illuminate\Http\Response
     */
    public function getOptions(Request $request)
    {
        $principals = Project::distinct('principal')->get([ 'principal' ])->toArray();

        $newPrincipals = [];
        foreach ($principals as $principal)
        {
            $tmp = [];
            $tmp['id'] = $principal['id'];
            $tmp['name'] = $principal['name'];
            $tmp['email'] = $principal['email'];
            $newPrincipals[] = $tmp;
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'principals' => $newPrincipals ] ]);
    }

    /**
     * create index of the project.
     *
     * @return \Illuminate\Http\Response
     */
    public function createIndex(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -14006);
        }
        if ($project->principal['id'] !== $this->user->id && !$this->user->hasAccess('sys_admin'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        Schema::collection('issue_' . $project->key, function($col) {
            $col->index('type');
            $col->index('state');
            $col->index('resolution');
            $col->index('priority');
            $col->index('created_at');
            $col->index('updated_at');
            $col->index('epic');
            $col->index('module');
            $col->index('resolve_version');
            $col->index('labels');
            $col->index('no');
            $col->index('parent_id');
            $col->index('assignee.id');
            $col->index('reporter.id');
        });
        Schema::collection('activity_' . $project->key, function($col) {
            $col->index('event_key');
        });
        Schema::collection('comments_' . $project->key, function($col) {
            $col->index('issue_id');
        });
        Schema::collection('issue_his_' . $project->key, function($col) {
            $col->index('issue_id');
        });
        Schema::collection('document_' . $project->key, function($col) {
            $col->index('parent');
        });
        Schema::collection('wiki_' . $project->key, function($col) {
            $col->index('parent');
        });

        return Response()->json([ 'ecode' => 0, 'data' => $project ]);
    }

    /**
     * create index of all selected projects.
     *
     * @return \Illuminate\Http\Response
     */
    public function createMultiIndex(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected projects cannot been empty.', -14007);
        }

        foreach ($ids as $id)
        {
            $project = Project::find($id);
            if (!$project)
            {
                continue;
            }

            Schema::collection('issue_' . $project->key, function($col) {
                $col->index('type');
                $col->index('state');
                $col->index('resolution');
                $col->index('priority');
                $col->index('created_at');
                $col->index('updated_at');
                $col->index('module');
                $col->index('epic');
                $col->index('resolve_version');
                $col->index('labels');
                $col->index('no');
                $col->index('assignee.id');
                $col->index('reporter.id');
            });
            Schema::collection('activity_' . $project->key, function($col) {
                $col->index('event_key');
            });
            Schema::collection('comments_' . $project->key, function($col) {
                $col->index('issue_id');
            });
            Schema::collection('issue_his_' . $project->key, function($col) {
                $col->index('issue_id');
            });
            Schema::collection('document_' . $project->key, function($col) {
                $col->index('parent');
            });
            Schema::collection('wiki_' . $project->key, function($col) {
                $col->index('parent');
            });
        }
        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $ids ] ]);
    }

    /**
     * update status of all selected projects.
     *
     * @return \Illuminate\Http\Response
     */
    public function updMultiStatus(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected projects cannot been empty.', -14007);
        }

        $status = $request->input('status');
        if (!isset($status) || !$status)
        {
            throw new \InvalidArgumentException('the status cannot be empty.', -14008);
        }

        $newIds = [];
        foreach ($ids as $id)
        {
            $newIds[] = new ObjectID($id);
        }

        Project::whereRaw([ '_id' => [ '$in' => $newIds ] ])->update([ 'status' => $status ]);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $ids ] ]);
    }

    /**
     * search project by the name or code.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $query = DB::collection('project');

        $s = $request->input('s');
        if (isset($s) && $s)
        {
            $query->where(function ($query) use ($s) {
                $query->where('key', 'like', '%' . $s . '%')->orWhere('name', 'like', '%' . $s . '%');
            });
        }

        $projects = $query->take(10)->get([ 'name', 'key' ]);

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($projects) ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = DB::collection('project');

        $principal_id = $request->input('principal_id');
        if (isset($principal_id) && $principal_id)
        {
            $query = $query->where('principal.id', $principal_id);
        }

        $status = $request->input('status');
        if (isset($status) && $status !== 'all')
        {
            $query = $query->where('status', $status);
        }

        $name = $request->input('name');
        if (isset($name) && $name)
        {
            $query->where(function ($query) use ($name) {
                $query->where('key', 'like', '%' . $name . '%')->orWhere('name', 'like', '%' . $name . '%');
            });
        }

        // get total
        $total = $query->count();

        $query->orderBy('created_at', 'desc');

        $page_size = 30;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $projects = $query->get([ 'name', 'key', 'description', 'status', 'principal' ]);
        foreach ($projects as $key => $project)
        {
            $projects[$key]['principal']['nameAndEmail'] = $project['principal']['name'] . '(' . $project['principal']['email'] . ')';
        }

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($projects), 'options' => [ 'total' => $total, 'sizePerPage' => $page_size ] ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $syssetting = SysSetting::first();
        $allow_create_project = isset($syssetting->properties['allow_create_project']) ? $syssetting->properties['allow_create_project'] : 0;        
        if ($allow_create_project !== 1 && !$this->user->hasAccess('sys_admin'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $insValues = [];

        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -14000);
        }
        $insValues['name'] = $name;

        $key = $request->input('key');
        if (!$key)
        {
            throw new \InvalidArgumentException('project key cannot be empty.', -14001);
        }
        if (Project::Where('key', $key)->exists())
        {
            throw new \InvalidArgumentException('project key has been taken.', -14002);
        }
        $insValues['key'] = $key;

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
                throw new \InvalidArgumentException('the user is not exists.', -14003);
            }
            $insValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' => $principal_info->email ];
        }

        $description = $request->input('description');
        if (isset($description) && $description)
        {
            $insValues['description'] = $description;
        }

        $insValues['category'] = 1;
        $insValues['status'] = 'active';
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        // save the project
        $project = Project::create($insValues); //fix me
        // add issue-type template to project
        $this->initialize($project->key);
        // trigger add user to usrproject
        Event::fire(new AddUserToRoleEvent([ $insValues['principal']['id'] ], $key));

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
    public function initialize($key)
    {
        $types = Type::where('project_key', '$_sys_$')->get()->toArray();
        foreach ($types as $type)
        {
            Type::create(array_only($type, [ 'name', 'abb', 'screen_id', 'workflow_id', 'sn', 'type', 'disabled', 'default' ]) + [ 'project_key' => $key ]);
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
            return Response()->json(['ecode' => -14004, 'emsg' => 'the project does not exist.']);
        }

        if ($project->status !== 'active')
        {
            return Response()->json(['ecode' => -14009, 'emsg' => 'the project has been closed.']);
        }

        // get action allow of the project.
        $permissions = Acl::getPermissions($this->user->id, $project->key);
        if ($this->user->id === $project->principal['id'] || $this->user->email === 'admin@action.view')
        {
            !in_array('view_project', $permissions) && $permissions[] = 'view_project';
            !in_array('manage_project', $permissions) && $permissions[] = 'manage_project';
        }

        //if (!$permissions)
        //{
        //    $isMember = UserProject::where('user_id', $this->user->id)
        //        ->where('project_key', $key)
        //        ->where('link_count', '>', 0)
        //        ->exists();
        //    if ($isMember)
        //    {
        //        $permissions[] = 'view_project';
        //    }
        //}
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

        // record the project access date
        if (in_array('view_project', $permissions))
        {
            AccessProjectLog::where('project_key', $key)
                ->where('user_id', $this->user->id)
                ->delete();
            AccessProjectLog::create([ 'project_key' => $key, 'user_id' => $this->user->id, 'latest_access_time' => time() ]);
        }

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
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -14000);
            }
            $updValues['name'] = $name;
        }
        // check is user is available
        $principal = $request->input('principal');
        if (isset($principal))
        {
            if (!$principal)
            {
                throw new \InvalidArgumentException('the principal must be appointed.', -14005);
            }

            $principal_info = Sentinel::findById($principal);
            if (!$principal_info)
            {
                throw new \InvalidArgumentException('the user is not exists.', -14003);
            }
            $updValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' =>  $principal_info->email ]; 
        }

        $description = $request->input('description');
        if (isset($description))
        {
            $updValues['description'] = $description;
        }

        $status = $request->input('status');
        if (isset($status) && in_array($status, [ 'active', 'closed' ]))
        {
            $updValues['status'] = $status;
        }

        $project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -14004);
        }
        if ($project->principal['id'] !== $this->user->id && !$this->user->hasAccess('sys_admin'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $old_principal = $project->principal;
        $project->fill($updValues)->save();

        if (isset($principal))
        {
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
    	$project = Project::find($id);
        if (!$project)
        {
            throw new \UnexpectedValueException('the project does not exist.', -14004);
        }

        $project_key = $project->key;
        //$related_cols = [ 'version', 'module', 'board', 'epic', 'sprint', 'sprint_log', 'searcher', 'access_project_log', 'access_board_log', 'user_group_project', 'watch', 'acl_role', 'acl_roleactor', 'acl_role_permissions', 'oswf_definition' ];
        $unrelated_cols = [ 'system.indexes', 'users', 'persistences', 'throttle', 'project' ];
        // delete releted table
        $collections = DB::listCollections();
        foreach ($collections as $col)
        {
            $col_name = $col->getName();
            if (strpos($col_name, 'issue_') === 0 ||
                strpos($col_name, 'activity_') === 0 ||
                strpos($col_name, 'comments_') === 0 ||
                strpos($col_name, 'document_') === 0 ||
                strpos($col_name, 'wiki_') === 0 ||
                in_array($col_name, $unrelated_cols))
            {
                continue;
            }
    
            DB::collection($col_name)->where('project_key', $project_key)->delete();
        }

        // delete the collections
        Schema::drop('issue_' . $project_key);
        Schema::drop('issue_his_' . $project_key);
        Schema::drop('activity_' . $project_key);
        Schema::drop('comments_' . $project_key);
        Schema::drop('document_' . $project_key);
        Schema::drop('wiki_' . $project_key);
        // delete from the project table
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
