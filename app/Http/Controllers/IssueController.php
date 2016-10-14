<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
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
        $page_size = 3;

        $where = array_except($request->all(), [ 'orderBy', 'page' ]) ?: [];
        foreach ($where as $key => $val)
        {
            if ($key === 'assignee' || $key === 'reporter')
            {
                $where[$key] = [ 'id' => $val ];
            }
        }

        $orderBy = $request->input('orderBy') ?: '';
        if ($orderBy)
        {
            $orderBy = explode(',', $orderBy);
        }

        $page = $request->input('page') ?: 1;

        $query = DB::collection('issue_' . $project_key);
        if ($where)
        {
            $query = $query->whereRaw($where);
        }
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

        $issues = $query->get();

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($issues) ]);
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

        // handle assignee
        $assignee = [];
        $assignee_id = $request->input('assignee');
        if (!$assignee_id)
        {
            $module_id = $request->input('module');
            if ($module_id)
            {
                if ($module['defaultAssignee'] === 'modulePrincipal')
                {
                    $assignee = Provider::getModulePrincipal($project_key, $module_id) ?: '';
                    $assignee_id = isset($assignee['_id']) ? $assignee['_id'] : '';
                }
                else if ($module['defaultAssignee'] === 'projectPrincipal') 
                {
                    $assignee = Provider::getProjectPrincipal($project_key) ?: '';
                    $assignee_id = isset($assignee['_id']) ? $assignee['_id'] : ''; 
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

        // get reporter(creator)
        $reporter = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $id = DB::collection($table)->insertGetId([ 'no' => $max_no, 'assignee' => $assignee, 'reporter' => $reporter, 'created_at' => new UTCDateTime(time()*1000) ] + array_only($request->all(), $valid_keys));

        $issue = DB::collection($table)->where('_id', $id)->first();
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
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($issue)]);
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
}
