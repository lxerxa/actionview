<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
use App\Project\Eloquent\File;
use Sentinel;
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
     * search issue.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request, $project_key)
    {
        $query = DB::collection('issue_' . $project_key);

        if ($s = $request->input('s'))
        {
            $query->where('title', 'like', '%' . $s . '%');
            if (is_int($s + 0))
            {
                $query->orWhere('no', $s + 0);
            }
        }

        if ($limit = $request->input('limit'))
        {
            $limit = intval($limit) < 10 ? 10 : intval($limit);
        }
        else
        {
            $limit = 10;
        }

        $query->take($limit)->orderBy('created_at', 'asc');
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
        $reporter = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $id = DB::collection($table)->insertGetId([ 'no' => $max_no, 'assignee' => $assignee, 'reporter' => $reporter, 'created_at' => time() ] + $ttValues + array_only($request->all(), $valid_keys));

        $issue = DB::collection($table)->where('_id', $id)->first();
        // add to histroy table
        Provider::snap2His($project_key, $id, $schema);

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

        $issue['links'] = [];
        $links = DB::collection('issue_link_' . $project_key)->where('src', $id)->orWhere('dest', $id)->orderBy('created_at', 'asc')->get();
        $link_fields = ['_id', 'no', 'type', 'title'];
        foreach ($links as $link)
        {
            if ($link['src'] == $id)
            {
                $link['src'] = array_only($issue, $link_fields);
            }
            else
            {
                $src_issue = DB::collection('issue_' . $project_key)->where('_id', $link['src'])->first();
                $link['src'] = array_only($src_issue, $link_fields);
            }

            if ($link['dest'] == $id)
            {
                $link['dest'] = array_only($issue, $link_fields);
            }
            else
            {
                $dest_issue = DB::collection('issue_' . $project_key)->where('_id', $link['dest'])->first();
                $link['dest'] = array_only($dest_issue, $link_fields);
            }
            array_push($issue['links'], $link);
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
        $resolutions = Provider::getResolutionList($project_key, ['name', 'default']);
        // get priority list
        $priorities = Provider::getPriorityList($project_key, ['color', 'name', 'default']);
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

        $schema = Provider::getSchemaByType($request->input('type') ?: $issue['type']);
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

        $updValues = [];
        $assignee_id = $request->input('assignee');
        if ($assignee_id)
        {
            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name ];
                $updValues['assignee'] = $assignee;
            }
        }

        $modifier = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        DB::collection($table)->where('_id', $id)->update($updValues + $ttValues + [ 'modifier' => $modifier, 'updated_at' => time() ] + array_only($request->all(), $valid_keys));

        // add to histroy table
        Provider::snap2His($project_key, $id, $schema, array_keys(array_only($request->all(), $valid_keys)));

        return $this->show($project_key, $id); 
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

        $id = DB::collection($table)->insertGetId([ 'user' => $this->user->id, 'created_at' => time() ] + array_only($request->all(), [ 'name', 'query' ] ));

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

    /**
     * get the history records.
     *
     * @param  string  $id
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function getHistory($project_key, $id)
    {
        $changedRecords = [];
        $records = DB::collection('issue_his_' . $project_key)->where('issue_id', $id)->orderBy('operated_at', 'asc')->get();
        foreach ($records as $i => $item)
        {
            if ($i == 0)
            {
                $changedRecords[] = [ 'operation' => 'create', 'operator' => $item['operator'], 'operated_at' => $item['operated_at'] ];
            }
            else
            {
                $changed_items = [];
                $changed_items['operation'] = 'modify';
                $changed_items['operated_at'] = $item['operated_at'];
                $changed_items['operator'] = $item['operator'];

                $diff_items = []; $diff_keys = [];
                $after_data = $item['data'];
                $before_data = $records[$i - 1]['data'];

                foreach ($after_data as $key => $val)
                {
                    if (!isset($before_data[$key]) || $val != $before_data[$key])
                    {
                        $tmp = [];
                        $tmp['field'] = isset($val['name']) ? $val['name'] : '';
                        $tmp['after_value'] = isset($val['value']) ? $val['value'] : '';
                        $tmp['before_value'] = isset($before_data[$key]) && isset($before_data[$key]['value']) ? $before_data[$key]['value'] : '';

                        if (is_array($tmp['after_value']) && is_array($tmp['before_value']))
                        {
                            $diff1 = array_diff($tmp['after_value'], $tmp['before_value']);
                            $diff2 = array_diff($tmp['before_value'], $tmp['after_value']);
                            $tmp['after_value'] = implode(',', $diff1);
                            $tmp['before_value'] = implode(',', $diff2);
                        }
                        else
                        {
                            if (is_array($tmp['after_value']))
                            {
                                $tmp['after_value'] = implode(',', $tmp['after_value']);
                            }
                            if (is_array($tmp['before_value']))
                            {
                                $tmp['before_value'] = implode(',', $tmp['before_value']);
                            }
                        }
                        $diff_items[] = $tmp; 
                        $diff_keys[] = $key; 
                    }
                }

                foreach ($before_data as $key => $val)
                {
                    if (array_search($key, $diff_keys) !== false)
                    {
                        continue;
                    }

                    if (!isset($after_data[$key]) || $val != $after_data[$key])
                    {
                        $tmp = [];
                        $tmp['field'] = isset($val['name']) ? $val['name'] : '';
                        $tmp['before_value'] = isset($val['value']) ? $val['value'] : '';
                        $tmp['after_value'] = isset($after_data[$key]) && isset($after_data[$key]['value']) ? $after_data[$key]['value'] : '';
                        if (is_array($tmp['after_value']) && is_array($tmp['before_value']))
                        {
                            $diff1 = array_diff($tmp['after_value'], $tmp['before_value']);
                            $diff2 = array_diff($tmp['before_value'], $tmp['after_value']);
                            $tmp['after_value'] = implode(',', $diff1);
                            $tmp['before_value'] = implode(',', $diff2);
                        }
                        else
                        {
                            if (is_array($tmp['after_value']))
                            {
                                $tmp['after_value'] = implode(',', $tmp['after_value']);
                            }
                            if (is_array($tmp['before_value']))
                            {
                                $tmp['before_value'] = implode(',', $tmp['before_value']);
                            }
                        }

                        $diff_items[] = $tmp; 
                    }
                }

                if ($diff_items)
                {
                    $changed_items['data'] = $diff_items;
                    $changedRecords[] = $changed_items;
                }
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => array_reverse($changedRecords) ]);
    }
}
