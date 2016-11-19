<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
use App\Project\Eloquent\File;
use Sentinel;
use MongoDB\BSON\UTCDateTime;
use DB;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key)
    {
        $page_size = 10;

        $where = array_except($request->all(), [ 'orderBy', 'page', 'created_at', 'updated_at', 'title' ]) ?: [];
        foreach ($where as $key => $val)
        {
            if ($key === 'assignee' || $key === 'reporter')
            {
                $where[ $key . '.' . 'id' ] = $val;
                unset($where[$key]);
            }
        }

        $created_at = $request->input('created_at');
        $updated_at = $request->input('updated_at');
        $title = $request->input('title');

        $orderBy = $request->input('orderBy') ?: '';
        if ($orderBy)
        {
            $orderBy = explode(',', $orderBy);
        }

        $page = $request->input('page');

        $query = DB::collection('issue_' . $project_key);
        if ($where)
        {
            $query = $query->whereRaw($where);
        }
        if (isset($title) && $title)
        {
            $query->where('title', 'like', '%' . $title . '%');
        }
        if (isset($created_at) && $created_at)
        {
            if ($created_at == '1w')
            {
                $query->where('created_at', '>=', strtotime('-1 week'));
            }
            else if ($created_at == '2w')
            {
                $query->where('created_at', '>=', strtotime('-2 weeks'));
            }
            else if ($created_at == '1m')
            {
                $query->where('created_at', '>=', strtotime('-1 month'));
            }
            else if ($created_at == '-1m')
            {
                $query->where('created_at', '<', strtotime('-1 month'));
            }
        }
        if (isset($updated_at) && $updated_at)
        {
            if ($updated_at == '1w')
            {
                $query->where('updated_at', '>=', strtotime('-1 week'));
            }
            else if ($updated_at == '2w')
            {
                $query->where('updated_at', '>=', strtotime('-2 weeks'));
            }
            else if ($updated_at == '1m')
            {
                $query->where('updated_at', '>=', strtotime('-1 month'));
            }
            else if ($updated_at == '-1m')
            {
                $query->where('updated_at', '<', strtotime('-1 month'));
            }
        }

        // get total num
        $total = $query->count();

        if ($orderBy)
        {
            foreach ($orderBy as $val)
            {
                $val = explode(' ', trim($val));
                $field = array_shift($val);
                $sort = array_pop($val) ?: 'asc';
                $query = $query->orderBy($field, $sort);
            }
        }

        if ($page)
        {
            $query = $query->skip($page_size * ($page - 1))->take($page_size);
        }

        $query->orderBy('created_at', 'desc');
        $issues = $query->get();

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($issues), 'options' => [ 'total' => $total, 'sizePerPage' => $page_size ] ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $issue_type = $request->input('type');
        if (!$issue_type)
        {
            throw new \UnexpectedValueException('the issue type can not be empty.', -10002);
        }

        $schema = Provider::getSchemaByType($issue_type);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -10002);
        }
        $valid_keys = array_column($schema, 'key');
        array_push($valid_keys, 'type');

        // handle timetracking
        $ttValues = [];
        foreach ($schema as $field)
        {
            if ($field['type'] == 'TimeTracking')
            {
                $fieldValue = $request->input($field['key']);
                if (isset($fieldValue) && $fieldValue)
                {
                    if (!$this->ttCheck($fieldValue))
                    {
                        throw new \UnexpectedValueException('the format of timetracking is incorrect.', -10002);
                    }
                    $ttValues[$field['key']] = $this->ttHandle($fieldValue);
                }
            }
        }

        // handle assignee
        $assignee = [];
        $assignee_id = $request->input('assignee');
        if (!$assignee_id)
        {
            $module_id = $request->input('module');
            if ($module_id)
            {
                $module = Provider::getModuleById($project_key, $module_id);
                if (isset($module['defaultAssignee']) && $module['defaultAssignee'] === 'modulePrincipal')
                {
                    $assignee = $module['principal'] ?: '';
                    $assignee_id = isset($assignee['id']) ? $assignee['id'] : '';
                }
                else if (isset($module['defaultAssignee']) && $module['defaultAssignee'] === 'projectPrincipal') 
                {
                    $assignee = Provider::getProjectPrincipal($project_key) ?: '';
                    $assignee_id = isset($assignee['id']) ? $assignee['id'] : ''; 
                }
            }
        }
        if ($assignee_id)
        {
            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name ];
            }
        }
        if (!$assignee) 
        {
            $assignee = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];
        }

        // get reporter(creator)
        $reporter = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $id = DB::collection($table)->insertGetId([ 'no' => $max_no, 'assignee' => $assignee, 'reporter' => $reporter, 'created_at' => time() ] + $ttValues + array_only($request->all(), $valid_keys));

        $issue = DB::collection($table)->where('_id', $id)->first();
        // add to histroy table
        DB::collection('issue_his_' . $project_key)->insert([ 'issue_id' => $issue['_id']->__toString(), 'stamptime' => time() ] + array_except($issue, [ '_id' ]));

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($issue) ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $issue = DB::collection('issue_' . $project_key)->where('_id', $id)->first();
        $schema = Provider::getSchemaByType($issue['type']);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -10002);
        }

        foreach ($schema as $field)
        {
            if ($field['type'] === 'File' && isset($issue[$field['key']]) && $issue[$field['key']]) 
            {
                foreach ($issue[$field['key']] as $key => $fid)
                {
                    $issue[$field['key']][$key] = File::find($fid);
                }
            }
        }

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($issue)]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOptions($project_key)
    {
        // get project users
        $users = Provider::getUserList($project_key);
        // get state list
        $states = Provider::getStateList($project_key, ['name']);
        // get resolution list
        $resolutions = Provider::getResolutionList($project_key, ['name']);
        // get priority list
        $priorities = Provider::getPriorityList($project_key, ['color', 'name']);
        // get version list
        $versions = Provider::getVersionList($project_key, ['name']);
        // get module list
        $modules = Provider::getModuleList($project_key, ['name']);
        // get project types
        $types = Provider::getTypeListExt($project_key, [ 'assignee' => $users, 'state' => $states, 'resolution' => $resolutions, 'priority' => $priorities, 'version' => $versions, 'module' => $modules ]);
        $searchers = $this->getSearchers($project_key);

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange([ 'users' => $users, 'types' => $types, 'states' => $states, 'resolutions' => $resolutions, 'priorities' => $priorities, 'searchers' => $searchers ]) ]);
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
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -10002);
        }

        $schema = Provider::getSchemaByType($issue['type']);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -10002);
        }
        $valid_keys = array_column($schema, 'key');

        DB::collection($table)->where('_id', $id)->update(array_only($request->all(), $valid_keys) + [ 'updated_at' => new UTCDateTime(time()*1000) ]);

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange(DB::collection($table)->find($id)) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return array 
     */
    public function getSearchers($project_key)
    {
        $searchers = DB::collection('searcher_' . $project_key)->where('user', $this->user->id)->orderBy('created_at', 'asc')->get();
        return $searchers ?: [];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSearcher(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        $table = 'searcher_' . $project_key;

        if (DB::collection($table)->where('name', $name)->where('user', $this->user->id)->exists())
        {
            throw new \UnexpectedValueException('searcher name cannot be repeated', -10002);
        }

        $id = DB::collection($table)->insertGetId([ 'user' => $this->user->id, 'created_at' => new UTCDateTime(time()*1000) ] + array_only($request->all(), [ 'name', 'query' ] ));

        $searcher = DB::collection($table)->where('_id', $id)->first();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($searcher) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delSearcher($project_key, $id)
    {
        $table = 'searcher_' . $project_key;
        $searcher = DB::collection($table)->find($id);
        if (!$searcher)
        {
            throw new \UnexpectedValueException('the searcher does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
