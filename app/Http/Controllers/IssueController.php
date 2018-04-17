<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
use App\Project\Eloquent\File;
use App\Project\Eloquent\Watch;
use App\Project\Eloquent\Searcher;
use App\Project\Eloquent\Linked;

use App\Project\Eloquent\Board;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\BoardRankMap;

use App\Workflow\Workflow;
use App\System\Eloquent\SysSetting;
use App\Acl\Acl;
use Sentinel;
use DB;

use MongoDB\BSON\ObjectID;
use Maatwebsite\Excel\Facades\Excel;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key)
    {

        $where = array_only($request->all(), [ 'type', 'assignee', 'reporter', 'state', 'resolution', 'priority', 'resolve_version', 'epic' ]) ?: [];
        foreach ($where as $key => $val)
        {
            if ($key === 'assignee' || $key === 'reporter')
            {
                $users = explode(',', $val);
                if (in_array('me', $users))
                {
                    array_push($users, $this->user->id); 
                }

                $where[ $key . '.' . 'id' ] = [ '$in' => $users ];
                unset($where[$key]);
            }
            else
            {
                $where[$key] = [ '$in' => explode(',', $val) ];
            }
        }

        $sprint = $request->input('sprint');
        if (isset($sprint) && $sprint)
        {
            $where['sprints'] = intval($sprint);
        }

        $watched_issues = Watch::where('project_key', $project_key)
            ->where('user.id', $this->user->id)
            ->get()
            ->toArray();
        $watched_issue_ids = array_column($watched_issues, 'issue_id');

        $watcher = $request->input('watcher');
        if (isset($watcher) && $watcher === 'me')
        {
            $watchedIds = [];
            foreach ($watched_issue_ids as $id)
            {
                $watchedIds[] = new ObjectID($id);
            }
            $where['_id'] = [ '$in' => $watchedIds ]; 
        }

        $query = DB::collection('issue_' . $project_key);
        if ($where)
        {
            $query = $query->whereRaw($where);
        }

        $no = $request->input('no');
        if (isset($no) && $no)
        {
            $query->where('no', intval($no));
        }

        $title = $request->input('title');
        if (isset($title) && $title)
        {
            if (is_int($title + 0))
            {
                $query->where(function ($query) use ($title) {
                    $query->where('no', $title + 0)->orWhere('title', 'like', '%' . $title . '%');
                });
            }
            else
            {
                $query->where('title', 'like', '%' . $title . '%');
            }
        }

        $module = $request->input('module');
        if (isset($module) && $module)
        {
            $query->where(function ($query) use ($module) {
                $modules = explode(',', $module);
                foreach ($modules as $m)
                {
                    $query->orWhere('module', 'like', '%' . $m . '%');
                }
            });
        }

        $created_at = $request->input('created_at');
        if (isset($created_at) && $created_at)
        {
            if ($created_at == '1w')
            {
                $query->where('created_at', '>=', strtotime(date('Ymd', strtotime('-1 week'))));
            }
            else if ($created_at == '2w')
            {
                $query->where('created_at', '>=', strtotime(date('Ymd', strtotime('-2 weeks'))));
            }
            else if ($created_at == '1m')
            {
                $query->where('created_at', '>=', strtotime(date('Ymd', strtotime('-1 month'))));
            }
            else if ($created_at == '-1m')
            {
                $query->where('created_at', '<', strtotime(date('Ymd', strtotime('-1 month'))));
            }
        }

        $updated_at = $request->input('updated_at');
        if (isset($updated_at) && $updated_at)
        {
            if ($updated_at == '1w')
            {
                $query->where('updated_at', '>=', strtotime(date('Ymd', strtotime('-1 week'))));
            }
            else if ($updated_at == '2w')
            {
                $query->where('updated_at', '>=', strtotime(date('Ymd', strtotime('-2 weeks'))));
            }
            else if ($updated_at == '1m')
            {
                $query->where('updated_at', '>=', strtotime(date('Ymd', strtotime('-1 month'))));
            }
            else if ($updated_at == '-1m')
            {
                $query->where('updated_at', '<', strtotime(date('Ymd', strtotime('-1 month'))));
            }
        }

        $from = $request->input('from');
        $from_kanban_id = $request->input('from_kanban_id');
        if (isset($from)) 
        {
            if ($from === 'kanban')
            {
                $query->where(function ($query) {
                    $query->whereRaw([ 'resolve_version' => [ '$exists' => 0 ] ])->orWhere('resolve_version', '');
                });
            }
            else if (($from === 'scrum' || $from === 'backlog') && isset($from_kanban_id) && $from_kanban_id)
            {
                $active_sprint_issues = [];
                $active_sprint = Sprint::where('status', 'active')->first();
                if ($from === 'scrum' && !$active_sprint) 
                {
                    Response()->json([ 'ecode' => 0, 'data' => []]);
                }
                else if ($active_sprint && isset($active_sprint['issues']) && $active_sprint['issues'])
                {
                    $active_sprint_issues = $active_sprint['issues'];
                }

                $last_column_states = [];
                $board = Board::find($from_kanban_id);
                if ($board && isset($board->columns))
                {
                    $board_columns = $board->columns;
                    $last_column = array_pop($board_columns) ?: [];
                    if ($last_column && isset($last_column['states']) && $last_column['states'])
                    {
                        $last_column_states = $last_column['states']; 
                    }
                }
 
                $query->where(function ($query) use ($last_column_states, $active_sprint_issues) {
                    $query->whereRaw([ 'state' => [ '$nin' => $last_column_states ] ])->orWhereIn('no', $active_sprint_issues);
                });
            }
        }

        $query->where('del_flg', '<>', 1);

        // get total num
        $total = $query->count();

        $orderBy = $request->input('orderBy') ?: '';
        if ($orderBy)
        {
            $orderBy = explode(',', $orderBy);
            foreach ($orderBy as $val)
            {
                $val = explode(' ', trim($val));
                $field = array_shift($val);
                $sort = array_pop($val) ?: 'asc';
                $query = $query->orderBy($field, $sort);
            }
        }

        $query->orderBy('created_at', isset($from) && $from ? 'asc' : 'desc');

        $page_size = $request->input('limit') ? intval($request->input('limit')) : 50;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $issues = $query->get();

        if (isset($from) && $from == 'export')
        {
            $export_fields = $request->input('export_fields');
            $this->export($project_key, isset($export_fields) ? explode(',', $export_fields) : [], $issues);
            exit();
        }

        $cache_parents = [];
        foreach ($issues as $key => $issue)
        {
            // set issue watching flag
            if (in_array($issue['_id']->__toString(), $watched_issue_ids))
            {
                $issues[$key]['watching'] = true;
            }

            // get the parent issue
            if (isset($issue['parent_id']) && $issue['parent_id'])
            {
                if (isset($cache_parents[$issue['parent_id']]) && $cache_parents[$issue['parent_id']])
                {
                    $issues[$key]['parent'] = $cache_parents[$issue['parent_id']];
                }
                else
                {
                    $parent = DB::collection('issue_' . $project_key)->where('_id', $issue['parent_id'])->first();
                    $issues[$key]['parent'] = $parent ? array_only($parent, [ '_id', 'title', 'no', 'type', 'state' ]) : [];
                    $cache_parents[$issue['parent_id']] = $issues[$key]['parent'];
                }
                unset($issues[$key]['parent_id']);
            }
            else if (!isset($from))
            {
                $issues[$key]['hasSubtasks'] = DB::collection('issue_' . $project_key)->where('parent_id', $issue['_id']->__toString())->exists();
            }
        }

        if ($issues && isset($from) && $from)
        {
            $filter = $request->input('filter') ?: '';
            $board_types  = $request->input('type') ?: '';
            $issues = $this->arrangeIssues($project_key, $issues, $from, $from_kanban_id, $board_types, $filter === 'all');
        }

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

        $type = $request->input('type');
        if (isset($type))
        {
            if ($type == 'standard')
            {
                $query->where(function ($query) 
                    { 
                        $query->where('parent_id', '')->orWhereNull('parent_id')->orWhere('parent_id', 'exists', false);
                    });
   
            }
            if ($type == 'subtask')
            {
                $query->where(function ($query) 
                    { 
                        $query->where('parent_id', 'exists', true)->where('parent_id', '<>', '')->whereNotNull('parent_id');
                    });
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
            throw new \UnexpectedValueException('the issue type can not be empty.', -11100);
        }

        $schema = Provider::getSchemaByType($issue_type);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -11101);
        }
        $valid_keys = array_merge(array_column($schema, 'key'), [ 'type', 'parent_id' ]);

        // handle timetracking
        $insValues = [];
        foreach ($schema as $field)
        {
            $fieldValue = $request->input($field['key']);
            if (!isset($fieldValue) || !$fieldValue)
            {
                continue;
            }

            if ($field['type'] == 'TimeTracking')
            {
                if (!$this->ttCheck($fieldValue))
                {
                    throw new \UnexpectedValueException('the format of timetracking is incorrect.', -11102);
                }
                $insValues[$field['key']] = $this->ttHandle($fieldValue);
            }
            else if ($field['type'] == 'SingleUser')
            {
                $user_info = Sentinel::findById($fieldValue);
                if ($user_info)
                {
                    $insValues[$field['key']] = [ 'id' => $fieldValue, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                }
            }
            else if ($field['type'] == 'MultiUser')
            {
                $user_ids = explode(',', $fieldValue);
                $insValues[$field['key']] = [];
                foreach ($user_ids as $uid)
                {
                    $user_info = Sentinel::findById($uid);
                    if ($user_info)
                    {
                        array_push($insValues[$field['key']], [ 'id' => $uid, 'name' => $user_info->first_name, 'email' => $user_info->email ]);
                    }
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
                $module = Provider::getModuleById($module_id);
                if (isset($module['defaultAssignee']) && $module['defaultAssignee'] === 'modulePrincipal')
                {
                    $assignee2 = $module['principal'] ?: '';
                    $assignee_id = isset($assignee2['id']) ? $assignee2['id'] : '';
                }
                else if (isset($module['defaultAssignee']) && $module['defaultAssignee'] === 'projectPrincipal') 
                {
                    $assignee2 = Provider::getProjectPrincipal($project_key) ?: '';
                    $assignee_id = isset($assignee2['id']) ? $assignee2['id'] : ''; 
                }
            }
        }
        if ($assignee_id)
        {
            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
            }
        }
        if (!$assignee) 
        {
            $assignee = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        }
        $insValues['assignee'] = $assignee;

        $priority = $request->input('priority'); 
        if (!isset($priority) || !$priority)
        {
            $insValues['priority'] = Provider::getDefaultPriority($project_key);
        }

        //$resolution = $request->input('resolution'); 
        //if (!isset($resolution))
        //{
        //    $insValues['resolution'] = 'Unresolved'; 
        //}

        // get reporter(creator)
        $insValues['reporter'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $insValues['no'] = $max_no;

        // workflow initialize 
        $workflow = $this->initializeWorkflow($issue_type);
        $insValues = array_merge($insValues, $workflow);

        // created time
        $insValues['created_at'] = time();

        $id = DB::collection($table)->insertGetId($insValues + array_only($request->all(), $valid_keys));

        // add to histroy table
        Provider::snap2His($project_key, $id, $schema);
        // trigger event of issue created
        Event::fire(new IssueEvent($project_key, $id->__toString(), $insValues['reporter'], [ 'event_key' => 'create_issue' ]));

        return $this->show($project_key, $id->__toString());
    }

    /**
     * initialize the workflow by type.
     *
     * @param  int  $type
     * @return array 
     */
    public function initializeWorkflow($type)
    {
        // get workflow definition
        $wf_definition = Provider::getWorkflowByType($type);
        // create and start workflow instacne
        $wf_entry = Workflow::createInstance($wf_definition->id)->start([ 'caller' => $this->user->id ]);
        // get the inital step
        $initial_step = $wf_entry->getCurrentSteps()->first();
        $initial_state = $wf_entry->getStepMeta($initial_step->step_id, 'state');

        $ret['state'] = $initial_state;
        $ret['resolution'] = 'Unresolved';
        $ret['entry_id'] = $wf_entry->getEntryId();
        $ret['definition_id'] = $wf_definition->id;

        return $ret;
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
            throw new \UnexpectedValueException('the schema of the type is not existed.', -11101);
        }

        if (isset($issue['assignee']['id']))
        {
            $user = Sentinel::findById($issue['assignee']['id']);
            $issue['assignee']['avatar'] = isset($user->avatar) ? $user->avatar : '';
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

        // get avaliable actions for wf
        if (isset($issue['entry_id']) && $issue['entry_id'])
        {
            try {
                $wf = new Workflow($issue['entry_id']);
                $issue['wfactions'] = $wf->getAvailableActions([ 'project_key' => $project_key, 'issue_id' => $id, 'caller' => $this->user->id ]);
            } catch (Exception $e) {
                $issue['wfactions'] = [];
            }

            foreach ($issue['wfactions'] as $key => $action)
            {
                if (isset($action['screen']) && $action['screen'] && $action['screen'] != 'comments')
                {
                    $issue['wfactions'][$key]['schema'] = Provider::getSchemaByScreenId($project_key, $issue['type'], $action['screen']);
                }
            }
        }

        if (isset($issue['parent_id']) && $issue['parent_id']) {
            $issue['parent'] = DB::collection('issue_' . $project_key)->where('_id', $issue['parent_id'])->first(['no', 'type', 'title', 'state']);
        }
        else
        {
            $issue['hasSubtasks'] = DB::collection('issue_' . $project_key)->where('parent_id', $id)->exists();
        }

        $issue['subtasks'] = DB::collection('issue_' . $project_key)->where('parent_id', $id)->where('del_flg', '<>', 1)->orderBy('created_at', 'asc')->get(['no', 'type', 'title', 'state']);

        $issue['links'] = [];
        $links = DB::collection('linked')->where('src', $id)->orWhere('dest', $id)->where('del_flg', '<>', 1)->orderBy('created_at', 'asc')->get();
        $link_fields = ['_id', 'no', 'type', 'title', 'state'];
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

        $issue['watchers'] = array_column(Watch::where('issue_id', $id)->orderBy('_id', 'desc')->get()->toArray(), 'user');
        
        if (Watch::where('issue_id', $id)->where('user.id', $this->user->id)->exists())
        {
            $issue['watching'] = true;
        }

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($issue)]);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function wfactions($project_key, $id)
    {
        $issue = DB::collection('issue_' . $project_key)->where('_id', $id)->first();

        $wf = new Workflow($issue['entry_id']);
        $wfactions = $wf->getAvailableActions([ 'project_key' => $project_key, 'issue_id' => $id, 'caller' => $this->user->id ], true);
        foreach ($wfactions as $key => $action)
        {
            if (isset($action['screen']) && $action['screen'])
            {
                $wfactions[$key]['schema'] = Provider::getSchemaByScreenId($project_key, $issue['type'], $action['screen']);
            }
        }

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($wfactions)]);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function getOptions($project_key)
    {
        // get project users
        $users = Provider::getUserList($project_key);
        // get project users fix me
        $assignees = Provider::getAssignedUsers($project_key);
        // get state list
        $states = Provider::getStateOptions($project_key);
        // get resolution list
        $resolutions = Provider::getResolutionOptions($project_key);
        // get priority list
        $priorities = Provider::getPriorityOptions($project_key);
        // get version list
        $versions = Provider::getVersionList($project_key, ['name']);
        // get module list
        $modules = Provider::getModuleList($project_key, ['name']);
        // get project epics
        $epics = Provider::getEpicList($project_key);
        // get project types
        $types = Provider::getTypeListExt($project_key, [ 'user' => $users, 'assignee' => $assignees, 'state' => $states, 'resolution' => $resolutions, 'priority' => $priorities, 'version' => $versions, 'module' => $modules, 'epic' => $epics ]);
        // get project sprints
        $sprint_nos = [];
        $sprints = Provider::getSprintList($project_key);
        foreach ($sprints as $sprint)
        {
            $sprint_nos[] = strval($sprint['no']);
        }
        // get defined fields
        $fields = Provider::getFieldList($project_key, ['key', 'name', 'type']);
        // get defined searchers
        $searchers = $this->getSearchers($project_key, ['name', 'query']);
        // get timetrack options
        $timetrack = $this->getTimeTrackSetting();

        return Response()->json([ 
            'ecode' => 0, 
            'data' => parent::arrange([ 
                'users' => $users, 
                'assignees' => $assignees, 
                'types' => $types, 
                'states' => $states, 
                'resolutions' => $resolutions, 
                'priorities' => $priorities, 
                'modules' => $modules, 
                'versions' => $versions, 
                'epics' => $epics,
                'sprints' => $sprint_nos,
                'searchers' => $searchers, 
                'timetrack' => $timetrack, 
                'fields' => $fields 
            ]) 
        ]);
    }

    /**
     * update issue assignee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function setAssignee(Request $request, $project_key, $id)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $updValues = []; $assignee = [];
        $assignee_id = $request->input('assignee');
        if (isset($assignee_id) && $assignee_id)
        {
            if ($assignee_id === 'me')
            {
                 if (!Acl::isAllowed($this->user->id, 'assigned_issue', $project_key))
                 {
                     return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                 }

                 $assignee = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
                 $updValues['assignee'] = $assignee;
            }
            else
            {
                if (!Acl::isAllowed($this->user->id, 'assign_issue', $project_key))
                {
                    return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                }

                $user_info = Sentinel::findById($assignee_id);
                if ($user_info)
                {
                    $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                    $updValues['assignee'] = $assignee;
                }
            }
        }
        else
        {
            throw new \UnexpectedValueException('the issue assignee cannot be empty.', -11104);
        }

        // issue assignee has no change.
        if ($assignee['id'] === $issue['assignee']['id'])
        {
            return $this->show($project_key, $id);
        }

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();
        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, null, [ 'assignee' ]);
        // trigger event of issue edited
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'assign_issue', 'data' => [ 'old_user' => $issue['assignee'], 'new_user' => $assignee ] ]));

        return $this->show($project_key, $id);
     }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        if (!Acl::isAllowed($this->user->id, 'edit_issue', $project_key) && !Acl::isAllowed($this->user->id, 'exec_workflow', $project_key))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        if (!$request->all())
        {
            return $this->show($project_key, $id); 
        }

        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $schema = Provider::getSchemaByType($request->input('type') ?: $issue['type']);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -11101);
        }
        $valid_keys = array_merge(array_column($schema, 'key'), [ 'type', 'assignee', 'parent_id' ]);

        // handle timetracking
        $updValues = [];
        foreach ($schema as $field)
        {
            $fieldValue = $request->input($field['key']);
            if (!isset($fieldValue) || !$fieldValue)
            {
                continue;
            }

            if ($field['type'] == 'TimeTracking')
            {
                if (!$this->ttCheck($fieldValue))
                {
                    throw new \UnexpectedValueException('the format of timetracking is incorrect.', -11102);
                }
                $updValues[$field['key']] = $this->ttHandle($fieldValue);
            }
            else if ($field['type'] == 'SingleUser')
            {
                $user_info = Sentinel::findById($fieldValue);
                if ($user_info)
                {
                    $updValues[$field['key']] = [ 'id' => $fieldValue, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                }
            }
            else if ($field['type'] == 'MultiUser')
            {
                $user_ids = explode(',', $fieldValue);
                $updValues[$field['key']] = [];
                foreach ($user_ids as $uid)
                {
                    $user_info = Sentinel::findById($uid);
                    if ($user_info)
                    {
                        array_push($updValues[$field['key']], [ 'id' => $uid, 'name' => $user_info->first_name, 'email' => $user_info->email ]);
                    }
                }
            }
        }

        $assignee_id = $request->input('assignee');
        if ($assignee_id)
        {
            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                $updValues['assignee'] = $assignee;
            }
        }

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();


        DB::collection($table)->where('_id', $id)->update($updValues + array_only($request->all(), $valid_keys));

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, $schema, array_keys(array_only($request->all(), $valid_keys)));

        // trigger event of issue edited
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));

        return $this->show($project_key, $id); 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        
        $ids = [ $id ];
        // delete all subtasks of this issue
        $subtasks = DB::collection('issue_' . $project_key)->where('parent_id', $id)->get();
        foreach ($subtasks as $subtask)
        {
            $sub_id = $subtask['_id']->__toString();
            DB::collection($table)->where('_id', $sub_id)->update([ 'del_flg' => 1 ]);

            Event::fire(new IssueEvent($project_key, $sub_id, $user, [ 'event_key' => 'del_issue' ]));
            $ids[] = $sub_id;
        }
        // delete linked relation
        DB::collection('linked')->where('src', $id)->orWhere('dest', $id)->delete();
        // delete this issue
        DB::collection($table)->where('_id', $id)->update([ 'del_flg' => 1 ]);
        // trigger event of issue deleted 
        Event::fire(new IssueEvent($project_key, $id, $user, [ 'event_key' => 'del_issue' ]));

        return Response()->json(['ecode' => 0, 'data' => [ 'ids' => $ids ]]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  string  $project_key
     * @return array 
     */
    public function getSearchers($project_key, $fields=[])
    {
        $searchers = Searcher::whereRaw([ 'user' => $this->user->id, 'project_key' => $project_key ])
            ->orderBy('sn', 'asc')
            ->get($fields);
        return $searchers ?: [];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function addSearcher(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11105);
        }

        if (Searcher::whereRaw([ 'name' => $name, 'user' => $this->user->id, 'project_key' => $project_key ])->exists())
        {
            throw new \UnexpectedValueException('searcher name cannot be repeated', -11106);
        }

        $searcher = Searcher::create([ 'project_key' => $project_key, 'user' => $this->user->id, 'sn' => time() ] + $request->all());
        return Response()->json([ 'ecode' => 0, 'data' => $searcher ]);
    }

    /**
     * update sort or delete searcher etc..
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return void
     */
    public function handleSearcher(Request $request, $project_key)
    {
        $table = 'searcher';
        // set searcher sort.
        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            // update flag
            DB::collection($table)->where('user', $this->user->id)->where('project_key', $project_key)->update([ 'flag' => 1 ]);

            $i = 1;
            foreach ($sequence as $searcher_id)
            {
                $searcher = [];
                $searcher['sn'] = $i++;
                $searcher['flag'] = 2;
                DB::collection($table)->where('_id', $searcher_id)->update($searcher);
            }

            // delete seachers
            DB::collection($table)->where('user', $this->user->id)->where('project_key', $project_key)->where('flag', 1)->delete();
        }

        return Response()->json([ 'ecode' => 0, 'data' => $this->getSearchers($project_key) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delSearcher($project_key, $id)
    {
        $searcher = Searcher::find($id);
        if (!$searcher || $searcher->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the searcher does not exist or is not in the project.', -11107);
        }

        Searcher::destroy($id);

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * get the history records.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function getHistory($project_key, $id)
    {
        $changedRecords = [];
        $records = DB::collection('issue_his_' . $project_key)->where('issue_id', $id)->orderBy('_id', 'asc')->get();
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
                    if (!isset($before_data[$key]) || $val !== $before_data[$key])
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

                    if (!isset($after_data[$key]) || $val !== $after_data[$key])
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

    /**
     * workflow action.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @param  string  $action_id
     * @return \Illuminate\Http\Response
     */
    public function doAction(Request $request, $project_key, $id, $workflow_id, $action_id)
    {
        $entry = new Workflow($workflow_id);
        $entry->doAction($action_id, [ 'project_key' => $project_key, 'issue_id' => $id, 'caller' => $this->user->id ] + array_only($request->all(), [ 'comments' ]));
        return $this->show($project_key, $id); 
    }

    /**
     * workflow action.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function watch(Request $request, $project_key, $id)
    {
        Watch::where('issue_id', $id)->where('user.id', $this->user->id)->delete();

        $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $flag = $request->input('flag');
        if (isset($flag) && $flag)
        {
            Watch::create([ 'project_key' => $project_key, 'issue_id' => $id, 'user' => $cur_user ]);
            // trigger event of issue watched 
            Event::fire(new IssueEvent($project_key, $id, $cur_user, [ 'event_key' => 'watched_issue' ]));
        }
        else
        {
            $flag = false;
            // trigger event of issue watched 
            Event::fire(new IssueEvent($project_key, $id, $cur_user, [ 'event_key' => 'unwatched_issue' ]));
        }
        
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id, 'user' => $cur_user, 'watching' => $flag]]);
    }

    /**
     * reset issue state.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function resetState(Request $request, $project_key, $id)
    {
        $issue = DB::collection('issue_' . $project_key)->where('_id', $id)->first();

        $updValues = [];
        // workflow initialize
        $workflow = $this->initializeWorkflow($issue['type']);
        $updValues = $workflow;

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();

        $table = 'issue_' . $project_key;
        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, null, array_keys($updValues));
        // trigger event of issue edited
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'reset_issue', 'snap_id' => $snap_id ]));

        return $this->show($project_key, $id);
    }

    /**
     * copy issue.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function copy(Request $request, $project_key)
    {
        $title = $request->input('title');
        if (!$title || trim($title) == '')
        {
            throw new \UnexpectedValueException('the issue title cannot be empty.', -11108);
        }

        $src_id = $request->input('source_id');
        if (!isset($src_id) || !$src_id)
        {
            throw new \UnexpectedValueException('the copied issue id cannot be empty.', -11109);
        }

        $src_issue = DB::collection('issue_' . $project_key)->where('_id', $src_id)->first();
        if (!$src_issue )
        {
            throw new \UnexpectedValueException('the copied issue does not exist or is not in the project.', -11103);
        }

        $schema = Provider::getSchemaByType($src_issue['type']);
        if (!$schema)
        {
            throw new \UnexpectedValueException('the schema of the type is not existed.', -11101);
        }

        $valid_keys = array_merge(array_column($schema, 'key'), [ 'type', 'parent_id', 'priority', 'assignee' ]);
        $insValues = array_only($src_issue, $valid_keys);

        $insValues['title'] = $title;
        // get reporter(creator)
        $insValues['reporter'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $insValues['no'] = $max_no;

        // workflow initialize
        $workflow = $this->initializeWorkflow($src_issue['type']);
        $insValues = array_merge($insValues, $workflow);
        // created time
        $insValues['created_at'] = time();

        $id = DB::collection($table)->insertGetId($insValues);

        $issue = DB::collection($table)->where('_id', $id)->first();
        // add to histroy table
        Provider::snap2His($project_key, $id, $schema);
        // create link of clone
        Linked::create([ 'src' => $src_id, 'relation' => 'is cloned by', 'dest' => $id->__toString(), 'creator' => $insValues['reporter'] ]);
        // trigger event of issue created 
        Event::fire(new IssueEvent($project_key, $id->__toString(), $insValues['reporter'], [ 'event_key' => 'create_issue' ]));
        // trigger event of link created 
        Event::fire(new IssueEvent($project_key, $src_id, $insValues['reporter'], [ 'event_key' => 'create_link', 'data' => [ 'relation' => 'is cloned by', 'dest' => $id->__toString() ] ]));

        return $this->show($project_key, $id->__toString());
    }

    /**
     * covert issue from subtask to standard or from standard to subtask.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function convert(Request $request, $project_key, $id)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $type = $request->input('type');
        if (!isset($type) || !$type)
        {
            throw new \UnexpectedValueException('the issue type cannot be empty.', -11100);
        }

        $parent_id = $request->input('parent_id');
        if (!isset($parent_id))
        {
            $parent_id = '';
        }
 
        $updValues = [];
        if ($parent_id)
        {
            // standard convert to subtask 
            $hasSubtasks = DB::collection($table)->where('parent_id', $id)->exists();
            if ($hasSubtasks)
            {
                throw new \UnexpectedValueException('the issue can not convert to subtask.', -11114);
            }

            $parent_issue = DB::collection($table)->find($parent_id);
            if (!$parent_issue)
            {
                throw new \UnexpectedValueException('the dest parent issue does not exist or is not in the project.', -11110);
            }
        }
        $updValues['parent_id'] = $parent_id;
        $updValues['type'] = $type;

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();
        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, null, [ 'parent_id', 'type' ]);
        // trigger event of issue moved
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ] ));

        return $this->show($project_key, $id);

    }

    /**
     * move issue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function move(Request $request, $project_key, $id)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $parent_id = $request->input('parent_id'); 
        if (!isset($parent_id) || !$parent_id)
        {
            throw new \UnexpectedValueException('the dest parent cannot be empty.', -11111);
        }
        $parent_issue = DB::collection($table)->find($parent_id);
        if (!$parent_issue)
        {
            throw new \UnexpectedValueException('the dest parent issue does not exist or is not in the project.', -11110);
        }

        if ($parent_id === $issue['parent_id'])
        {
            return $this->show($project_key, $id);
        }

        $updValues = [];
        $updValues['parent_id'] = $parent_id;
        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();
        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, null, [ 'parent_id' ]);
        // trigger event of issue moved
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'move_issue', 'data' => [ 'old_parent' => $issue['parent_id'], 'new_parent' => $parent_id ] ]));

        return $this->show($project_key, $id);
    }

    /**
     * release issue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function release(Request $request, $project_key) {
        $ids = $request->input('ids'); 
        if (!$ids)
        {
            throw new \UnexpectedValueException('the released issues cannot be empty.', -11132);
        }

        $version = $request->input('version');
        if (!$version)
        {
            throw new \UnexpectedValueException('the resolved version must be assigned.', -11131);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        foreach ($ids as $id)
        {
            DB::collection('issue_' . $project_key)->where('_id', $id)->update([ 'resolve_version' => $version ]);
            // add to histroy table
            $snap_id = Provider::snap2His($project_key, $id, null, [ 'resolve_version' ]);
            // trigger event of issue moved
            Event::fire(new IssueEvent($project_key, $id, $user, [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ] ));
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $ids ] ]);
    }

    /**
     * get timetrack setting.
     *
     * @return array 
     */
    function getTimeTrackSetting() 
    {
        $options = [ 'w2d' => 5, 'd2h' => 8 ];

        $setting = SysSetting::first();
        if ($setting && isset($setting->properties))
        {
            if (isset($setting->properties['week2day']))
            {
                $options['w2d'] = $setting->properties['week2day'];
            }
            if (isset($setting->properties['day2hour']))
            {
                $options['d2h'] = $setting->properties['day2hour'];
            }
        }
        return $options;
    }

    /**
     * classify issues by parent_id.
     *
     * @return array
     */
    public function classifyIssues($issues)
    {
        if (!$issues) { return []; }

        $classified_issues  = [];
        foreach ($issues as $issue)
        {
            if (isset($issue['parent']) && $issue['parent'])
            {
                if (isset($classified_issues[$issue['parent']['no']]) && $classified_issues[$issue['parent']['no']])
                {
                    $classified_issues[$issue['parent']['no']][] =  $issue;
                }
                else
                {
                    $classified_issues[$issue['parent']['no']] = [ $issue ];
                }
            }
            else
            {
                if (isset($classified_issues[$issue['no']]) && $classified_issues[$issue['no']])
                {
                    array_unshift($classified_issues[$issue['no']], $issue);
                }
                else
                {
                    $classified_issues[$issue['no']] = [ $issue ];
                }
            }
        }

        return $classified_issues;
    }

    /**
     * add avatar for issues
     *
     * @return array
     */
    public function addAvatar(&$issues)
    {
        $cache_avatars = [];
        foreach ($issues as $key => $issue)
        {
            //get assignee avatar for kanban
            if (!array_key_exists($issue['assignee']['id'], $cache_avatars))
            {
                $user = Sentinel::findById($issue['assignee']['id']);
                $cache_avatars[$issue['assignee']['id']] = isset($user->avatar) ? $user->avatar : '';
            }
            $issues[$key]['assignee']['avatar'] = $cache_avatars[$issue['assignee']['id']];
        }
        return;
    }

    /**
     * flat issues from 2d to 1d.
     *
     * @return array
     */
    public function flatIssues($classified_issues)
    {
        $issues = [];
        foreach ($classified_issues as $some)
        {
            foreach ($some as $one)
            {
                $issues[] = $one;
            }
        }
        return $issues;
    }

    /**
     * arrange issues for kanban.
     *
     * @return array 
     */
    public function arrangeIssues($project_key, $issues, $from, $from_board_id, $board_types='', $isUpdRank=false)
    {
        $board_issues = [];
        foreach ($issues as $key => $issue)
        {
            // filter subtask type
            if (isset($issues[$key]['parent']) && $issues[$key]['parent'])
            {
                if ($board_types && strpos($board_types, $issues[$key]['parent']['type']) === false)
                {
                    continue;
                }
            }
            $board_issues[] = $issue;
        }

        // classify the issues
        $classified_issues = $this->classifyIssues($board_issues);

        // whether the board is ranked
        $rankmap = BoardRankMap::where([ 'board_id' => $from_board_id ])->first();
        if (!$rankmap)
        {
            $issues = $this->flatIssues($classified_issues);

            $rank = [];
            foreach ($issues as $issue)
            {
                $rank[] = $issue['no'];
            }
            
            BoardRankMap::create([ 'board_id' => $from_board_id, 'rank' => $rank ]);

            if ($from === 'scrum')
            {
                $issues = $this->sprintFilter($project_key, $issues);
            }

            $this->addAvatar($issues);
            return $issues;
        }
 
        $sub2parent_map = []; 
        foreach ($board_issues as $issue)
        {
            if (isset($issue['parent']) && $issue['parent'])
            {
                $sub2parent_map[$issue['no']] = $issue['parent']['no'];
            }
        }

        $rank = $rankmap->rank; 
        foreach ($classified_issues as $no => $some)
        {
            if (count($some) <= 1) { continue; }

            $group_issues = [];
            foreach ($some as $one)
            {
                $group_issues[$one['no']] = $one;
            }

            $sorted_group_issues = [];
            foreach ($rank as $val)
            {
                if (isset($group_issues[$val]))
                {
                    $sorted_group_issues[$val] = $group_issues[$val];
                }
            }

            foreach ($group_issues as $no2 => $issue)
            {
                if (!isset($sorted_group_issues[$no2]))
                {
                    $sorted_group_issues[$no2] = $issue;
                }
            }
            $classified_issues[$no] = array_values($sorted_group_issues);

            // prevent the sort confusion 
            $parentInd = array_search($no, $classified_issues[$no]);
            if ($parentInd > 0)
            {
                array_splice($classified_issues[$no], $parentInd, 1);
                array_unshift($classified_issues[$no], $no);
            }
        }

        $sorted_issues = [];
        foreach ($rank as $val)
        {
            if (isset($classified_issues[$val]) && $classified_issues[$val])
            {
                $sorted_issues[$val] = $classified_issues[$val]; 
            }
            else
            {
                if (isset($sub2parent_map[$val]) && $sub2parent_map[$val])
                {
                    $parent = $sub2parent_map[$val];
                    if (!isset($sorted_issues[$parent]))
                    {
                        $sorted_issues[$parent] = $classified_issues[$parent]; 
                    }
                }
            }
        }

        // append some issues which is ranked
        foreach ($classified_issues as $key => $val)
        {
            if (!isset($sorted_issues[$key]))
            {
                $sorted_issues[$key] = $val;
            }
        }

        // convert array to ordered array
        $issues = $this->flatIssues($sorted_issues); 

        if ($isUpdRank)
        {
            $new_rank = [];
            foreach ($issues as $issue)
            {
                $new_rank[] = $issue['no'];
            }

            if (array_diff_assoc($new_rank, $rank) || array_diff_assoc($rank, $new_rank))
            {
                $rankmap = BoardRankMap::where('board_id', $from_board_id)->first();
                $rankmap && $rankmap->update([ 'rank' => $new_rank ]);
            }
        }

        if ($from === 'scrum')
        {
            $issues = $this->sprintFilter($project_key, $issues);
        }

        $this->addAvatar($issues);
        return $issues;
    }

    public function sprintFilter($project_key, $issues)
    {
        $active_sprint_issues = [];
        $active_sprint_issue_nos = [];
        $active_sprint = Sprint::where('project_key', $project_key)->where('status', 'active')->first();
        if ($active_sprint && isset($active_sprint->issues) && $active_sprint->issues)
        {
            $active_sprint_issue_nos = $active_sprint->issues;
        }

        foreach($issues as $issue)
        {
            if (in_array($issue['no'], $active_sprint_issue_nos))
            {
                $active_sprint_issues[] = $issue;
           }
        }

        return $active_sprint_issues;
    }

    public function getOptionsForExport($project_key)
    {
        $types = [];
        $type_list = Provider::getTypeList($project_key);
        foreach ($type_list as $type)
        {
            $types[$type->id] = $type->name;
        }

        $states = [];
        $state_list =  Provider::getStateOptions($project_key);
        foreach ($state_list as $state)
        {
            $states[$state['_id']] = $state['name'];
        }

        $resolutions = [];
        $resolution_list = Provider::getResolutionOptions($project_key);
        foreach ($resolution_list as $resolution)
        {
            $resolutions[$resolution['_id']] = $resolution['name'];
        }

        $priorities = [];
        $priority_list = Provider::getPriorityOptions($project_key);
        foreach ($priority_list as $priority)
        {
            $priorities[$priority['_id']] = $priority['name'];
        }

        $versions = [];
        $version_list = Provider::getVersionList($project_key);
        foreach($version_list as $version)
        {
            $versions[$version->id] = $version->name;
        }

        $modules = [];
        $module_list =  Provider::getModuleList($project_key);
        foreach ($module_list as $module)
        {
            $modules[$module->id] = $module->name;
        }

        $epics = [];
        $epic_list =  Provider::getEpicList($project_key);
        foreach ($epic_list as $epic)
        {
            $epics[$epic['_id']] = $epic['name'];
        }

        $fields = [];
        $field_list = Provider::getFieldList($project_key);
        foreach ($field_list as $field)
        {
            $tmp = [];
            $tmp['name'] = $field->name;
            $tmp['type'] = $field->type;
            $fields[$field->key] = $tmp;
        }

        $fields['no'] = [ 'name' => 'NO', 'type' => 'Number' ];
        $fields['type'] = [ 'name' => '类型', 'type' => 'Select' ];
        $fields['state'] = [ 'name' => '状态', 'type' => 'Select' ];
        $fields['created_at'] = [ 'name' => '创建时间', 'type' => 'DateTimePicker' ];
        $fields['updated_at'] = [ 'name' => '更新时间', 'type' => 'DateTimePicker' ];
        $fields['reporter'] = [ 'name' => '报告者', 'type' => '' ];
        $fields['sprints'] = [ 'name' => 'Sprint', 'type' => '' ];

        return [
          'types' => $types,
          'states' => $states,
          'resolutions' => $resolutions,
          'priorities' => $priorities,
          'versions' => $versions,
          'modules' => $modules,
          'epics' => $epics,
          'fields' => $fields,
        ];

    }

    public function export($project_key, $export_fields, $issues) 
    {
        $options = $this->getOptionsForExport($project_key);
        foreach ($options as $key => $val)
        {
            $$key = $val;
        }

        $headers = [];
        foreach ($export_fields as $fk)
        {
            $headers[] = isset($fields[$fk]) && $fields[$fk] ? $fields[$fk]['name'] : '';
        }

        $new_issues = [];
        foreach ($issues as $issue)
        {
            $tmp = [];
            foreach ($export_fields as $fk)
            {
                if (!isset($issue[$fk]) || (!$issue[$fk] && $issue[$fk] !== 0))
                {
                    $tmp[] = '';
                    continue;
                }

                if ($fk == 'assignee' || $fk == 'reporter')
                {
                    $tmp[] = isset($issue[$fk]['name']) ? $issue[$fk]['name'] : '';
                }
                else if ($fk == 'module')
                {
                    $new_modules = [];
                    $module_ids = explode(',', $issue[$fk]);
                    foreach ($module_ids as $id)
                    {
                        if (!isset($modules[$id]) || !$modules[$id])
                        {
                            continue;
                        }
                        $new_modules[] = $modules[$id];
                    }
                    $tmp[] = implode(',', $new_modules);
                }
                else if ($fk == 'type')
                {
                    $tmp[] = isset($types[$issue[$fk]]) && $types[$issue[$fk]] ? $types[$issue[$fk]] : '';
                }
                else if ($fk == 'priority')
                {
                    $tmp[] = isset($priorities[$issue[$fk]]) && $priorities[$issue[$fk]] ? $priorities[$issue[$fk]] : '';
                }
                else if ($fk == 'state')
                {
                    $tmp[] = isset($states[$issue[$fk]]) && $states[$issue[$fk]] ? $states[$issue[$fk]] : '';
                }
                else if ($fk == 'resolution')
                {
                    $tmp[] = isset($resolutions[$issue[$fk]]) && $resolutions[$issue[$fk]] ? $resolutions[$issue[$fk]] : '';
                }
                else if ($fk == 'epic')
                {
                    $tmp[] = isset($epics[$issue[$fk]]) && $epics[$issue[$fk]] ? $epics[$issue[$fk]] : '';
                }
                else if ($fk == 'sprints')
                {
                    $tmp[] = 'Sprint ' . implode(',', $issue[$fk]);
                }
                else if (isset($fields[$fk]) && $fields[$fk])
                {
                    if ($fields[$fk]['type'] == 'DateTimePicker')
                    {
                        $tmp[] = date('Y-m-d H:i:s', $issue[$fk]);
                    }
                    else if ($fields[$fk]['type'] == 'DatePicker')
                    {
                        $tmp[] = date('Y-m-d', $issue[$fk]);
                    }
                    else if ($fields[$fk]['type'] == 'SingleVersion' || $fields[$fk]['type'] == 'MultiVersion')
                    {
                        $new_versions = [];
                        $version_ids = explode(',', $issue[$fk]);
                        foreach ($version_ids as $id)
                        {
                            if (isset($versions[$id]) && $versions[$id])
                            {
                                $new_versions[] = $versions[$id];
                            }
                        }
                        $tmp[] = implode(',', $new_versions);
                    } 
                    else if ($fields[$fk]['type'] == 'SingleUser')
                    {
                        $tmp[] = isset($issue[$fk]['name']) ? $issue[$fk]['name'] : '';
                    }
                    else if ($fields[$fk]['type'] == 'MultiUser')
                    {
                        $new_users = [];
                        foreach ($issue[$fk] as $user)
                        {
                            if (isset($user['name']) && $user['name'])
                            {
                                $new_users[] = $user['name'];
                            }
                        }
                        $tmp[] = implode(',', $new_users);
                    }
                    else
                    {
                        if (is_array($issue[$fk]))
                        {
                            $tmp[] = implode(',', $issue[$fk]);
                        }
                        else
                        {
                            $tmp[] = $issue[$fk];
                        }
                    }
                }
                else
                {
                    $tmp[] = $issue[$fk];
                }
            }
            $new_issues[] = $tmp;
        }

        $file_name = 'issue-list';
        Excel::create($file_name, function ($excel) use($headers, $new_issues) {
            $excel->sheet('Sheetname', function ($sheet) use($headers, $new_issues) {
                $sheet->appendRow($headers);
                foreach ($new_issues as $issue)
                {
                    $sheet->appendRow($issue);
                }
            });
        })->download('xls');
    }
}
