<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;
use App\Events\VersionEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
use App\Project\Eloquent\File;
use App\Project\Eloquent\Watch;
use App\Project\Eloquent\UserIssueFilters;
use App\Project\Eloquent\UserIssueListColumns;
use App\Project\Eloquent\ProjectIssueListColumns;
use App\Project\Eloquent\Linked;
use App\Project\Eloquent\Worklog;
use App\Project\Eloquent\Version;

use App\Project\Eloquent\Board;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\BoardRankMap;

use App\Project\Eloquent\Labels;

use App\Workflow\Workflow;
use App\System\Eloquent\SysSetting;
use App\System\Eloquent\CalendarSingular;
use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel;
use DB;
use Exception;

use MongoDB\BSON\ObjectID;
use Maatwebsite\Excel\Facades\Excel;

class IssueController extends Controller
{
    use ExcelTrait, TimeTrackTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key)
    {
        $where = $this->getIssueQueryWhere($project_key, $request->all());
        $query = DB::collection('issue_' . $project_key)->whereRaw($where); 

        $from = $request->input('from');
        $from_kanban_id = $request->input('from_kanban_id');
        if (isset($from) && in_array($from, [ 'kanban', 'active_sprint', 'backlog' ]) && isset($from_kanban_id) && $from_kanban_id) 
        {
            $board = Board::find($from_kanban_id);
            if ($board && isset($board->query) && $board->query)
            {
                $global_query = $this->getIssueQueryWhere($project_key, $board->query);
                $query->whereRaw($global_query);
            }

            if ($from === 'kanban')
            {
                $query->where(function ($query) {
                    $query->whereRaw([ 'resolve_version' => [ '$exists' => 0 ] ])->orWhere('resolve_version', '');
                });
            }
            else if ($from === 'active_sprint' || $from === 'backlog')
            {
                $active_sprint_issues = [];
                $active_sprint = Sprint::where('project_key', $project_key)->where('status', 'active')->first();
                if ($from === 'active_sprint' && !$active_sprint) 
                {
                    Response()->json([ 'ecode' => 0, 'data' => []]);
                }
                else if ($active_sprint && isset($active_sprint['issues']) && $active_sprint['issues'])
                {
                    $active_sprint_issues = $active_sprint['issues'];
                }

                $last_column_states = [];
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

        $query->orderBy('_id', isset($from) && $from != 'gantt' ? 'asc' : 'desc');

        $page_size = $request->input('limit') ? intval($request->input('limit')) : 50;
        //$page_size = 200;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $issues = $query->get();

        if (isset($from) && $from == 'export')
        {
            $export_fields = $request->input('export_fields');
            $this->export($project_key, isset($export_fields) ? explode(',', $export_fields) : [], $issues);
            exit();
        }

        $watched_issue_ids = [];
        if (!isset($from) || !$from)
        {
            $watched_issues = Watch::where('project_key', $project_key)
                ->where('user.id', $this->user->id)
                ->get()
                ->toArray();
            $watched_issue_ids = array_column($watched_issues, 'issue_id');
        }

        $cache_parents = [];
        $issue_ids = [];
        foreach ($issues as $key => $issue)
        {
            $issue_ids[] = $issue['_id']->__toString();
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
            }

            if (!isset($from))
            {
                $issues[$key]['hasSubtasks'] = DB::collection('issue_' . $project_key)->where('parent_id', $issue['_id']->__toString())->exists();
            }
        }

        if ($issues && isset($from) && in_array($from, [ 'kanban', 'active_sprint', 'backlog', 'his_sprint' ]))
        {
            $filter = $request->input('filter') ?: '';
            $issues = $this->arrangeIssues($project_key, $issues, $from, $from_kanban_id, $filter === 'all');
        }

        $options = [ 'total' => $total, 'sizePerPage' => $page_size ];

        if (isset($from) && $from == 'gantt')
        {
            foreach ($issues as $key => $issue) 
            {
                if (!isset($issue['parent_id']) || !$issue['parent_id'] || in_array($issue['parent_id'], $issue_ids)) 
                {
                    continue;
                }
                if (isset($issue['parent']) && $issue['parent']) 
                {
                    $issues[] = $issue['parent'];
                    $issue_ids[] = $issue['parent_id'];
                }
            }

            $singulars = CalendarSingular::all();
            $new_singulars = [];
            foreach ($singulars as $singular)
            {
                $tmp = [];
                $tmp['notWorking'] = $singular->type == 'holiday' ? 1 : 0;
                $tmp['date'] = $singular->date;
                $new_singulars[] = $tmp;
            }
            $options['singulars'] = $new_singulars;
            $options['today'] = date('Y/m/d');
        }

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($issues), 'options' => $options ]);
    }

    /**
     * search issue.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request, $project_key)
    {
        $query = DB::collection('issue_' . $project_key)->where('del_flg', '<>', 1);

        if ($s = $request->input('s'))
        {
            if (is_numeric($s) && strpos($s, '.') === false)
            {
                $query->where(function ($query) use ($s) {
                    $query->where('no', $s + 0)->orWhere('title', 'like', '%' . $s . '%');
                });
            }
            else
            {
                $query->where('title', 'like', '%' . $s . '%');
            }
        }

        $type = $request->input('type');
        if (isset($type))
        {
            if ($type == 'standard')
            {
                $query->where(function ($query) { 
                    $query->where('parent_id', '')->orWhereNull('parent_id')->orWhere('parent_id', 'exists', false);
                });
   
            } 
            else if ($type == 'subtask')
            {
                $query->where(function ($query) { 
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

        if (!$this->requiredCheck($schema, $request->all(), 'create'))
        {
            throw new \UnexpectedValueException('the required field is empty.', -11121);
        }

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
                $insValues[$field['key'] . '_m'] = $this->ttHandleInM($insValues[$field['key']]);
            }
            else if ($field['type'] == 'DatePicker' || $field['type'] == 'DateTimePicker')
            {
                if ($this->isTimestamp($fieldValue) === false)
                {
                    throw new \UnexpectedValueException('the format of datepicker field is incorrect.', -11122);
                }
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
                $user_ids = $fieldValue;
                $new_user_ids = [];
                $insValues[$field['key']] = [];
                foreach ($user_ids as $uid)
                {
                    $user_info = Sentinel::findById($uid);
                    if ($user_info)
                    {
                        array_push($insValues[$field['key']], [ 'id' => $uid, 'name' => $user_info->first_name, 'email' => $user_info->email ]);
                        $new_user_ids[] = $uid;
                    }
                }
                $insValues[$field['key'] . '_ids'] = $new_user_ids;
            }
        }

        // handle assignee
        $assignee = [];
        $assignee_id = $request->input('assignee');
        if (!$assignee_id)
        {
            $module_ids = $request->input('module');
            if ($module_ids)
            {
                //$module_ids = explode(',', $module_ids);
                $module = Provider::getModuleById($module_ids[0]);
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
            if ($assignee_id != $this->user->id && !$this->isPermissionAllowed($project_key, 'assigned_issue', $assignee_id))
            {
                return Response()->json(['ecode' => -11118, 'emsg' => 'the assigned user has not assigned-issue permission.']);
            }

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

        //$priority = $request->input('priority'); 
        //if (!isset($priority) || !$priority)
        //{
        //    $insValues['priority'] = Provider::getDefaultPriority($project_key);
        //}

        $resolution = $request->input('resolution'); 
        if (!isset($resolution) || !$resolution)
        {
            $insValues['resolution'] = 'Unresolved'; 
        }

        // get reporter(creator)
        $insValues['reporter'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $insValues['created_at'] = time();

        $table = 'issue_' . $project_key;
        $max_no = DB::collection($table)->count() + 1;
        $insValues['no'] = $max_no;

        // workflow initialize 
        $workflow = $this->initializeWorkflow($issue_type);
        $insValues = $insValues + $workflow;

        $valid_keys = $this->getValidKeysBySchema($schema);
        // merge all fields
        $insValues = $insValues + array_only($request->all(), $valid_keys);

        // insert into the table
        $id = DB::collection($table)->insertGetId($insValues);

        // add to histroy table
        Provider::snap2His($project_key, $id, $schema);
        // trigger event of issue created
        Event::fire(new IssueEvent($project_key, $id->__toString(), $insValues['reporter'], [ 'event_key' => 'create_issue' ]));

        // create the Labels for project
        if (isset($insValues['labels']) && $insValues['labels'])
        {
            $this->createLabels($project_key, $insValues['labels']);
        }

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
        $wf_entry = Workflow::createInstance($wf_definition->id, $this->user->id)->start([ 'caller' => $this->user->id ]);
        // get the inital step
        $initial_step = $wf_entry->getCurrentSteps()->first();
        $initial_state = $wf_entry->getStepMeta($initial_step->step_id, 'state');

        $ret['state'] = $initial_state;
        //$ret['resolution'] = 'Unresolved';
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

        if (isset($issue['parent_id']) && $issue['parent_id']) 
        {
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

        $comments_num = 0;
        $comments = DB::collection('comments_' . $project_key)
            ->where('issue_id', $id)
            ->get();
        foreach($comments as $comment)
        {
            $comments_num += 1;
            if (isset($comment['reply']))
            {
                $comments_num += count($comment['reply']);
            }
        }
        $issue['comments_num'] = $comments_num;

        $issue['gitcommits_num'] = DB::collection('git_commits_' . $project_key)
            ->where('issue_id', $id)
            ->count();

        $issue['worklogs_num'] = Worklog::Where('project_key', $project_key)
            ->where('issue_id', $id)
            ->count();

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
        // get project labels
        $labels = Provider::getLabelOptions($project_key);
        // get project types
        $types = Provider::getTypeListExt($project_key, [ 'user' => $users, 'assignee' => $assignees, 'state' => $states, 'resolution' => $resolutions, 'priority' => $priorities, 'version' => $versions, 'module' => $modules, 'epic' => $epics, 'labels' => $labels ]);
        // get project sprints
        $sprint_nos = [];
        $sprints = Provider::getSprintList($project_key);
        foreach ($sprints as $sprint)
        {
            $sprint_nos[] = strval($sprint['no']);
        }
        // get defined fields
        $fields = Provider::getFieldList($project_key);
        // get defined searchers
        $filters = Provider::getIssueFilters($project_key, $this->user->id);
        // get defined list columns
        $display_columns = Provider::getIssueDisplayColumns($project_key, $this->user->id);
        // get timetrack options
        $timetrack = $this->getTimeTrackSetting();
        // get issue link relations
        $relations = $this->getLinkRelations();

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
                'labels' => $labels, 
                'versions' => $versions, 
                'epics' => $epics,
                'sprints' => $sprint_nos,
                'filters' => $filters, 
                'display_columns' => $display_columns, 
                'timetrack' => $timetrack, 
                'relations' => $relations, 
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

        if (!$this->isPermissionAllowed($project_key, 'assign_issue'))
        {
            return Response()->json(['ecode' => -11116, 'emsg' => 'the current user has not assign-issue permission.']);
        }

        $updValues = []; $assignee = [];
        $assignee_id = $request->input('assignee');
        if (!isset($assignee_id) || !$assignee_id)
        {
            throw new \UnexpectedValueException('the issue assignee cannot be empty.', -11104);
        }

        if ($assignee_id === 'me')
        {
            if (!$this->isPermissionAllowed($project_key, 'assigned_issue'))
            {
                return Response()->json(['ecode' => -11117, 'emsg' => 'the current user has not assigned-issue permission.']);
            }

            $assignee = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
            $updValues['assignee'] = $assignee;
        }
        else
        {
            if (!$this->isPermissionAllowed($project_key, 'assigned_issue', $assignee_id))
            {
                return Response()->json(['ecode' => -11118, 'emsg' => 'the assigned user has not assigned-issue permission.']);
            }

            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                $updValues['assignee'] = $assignee;
            }
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
     * set issue labels.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function setLabels(Request $request, $project_key, $id)
    {
        if (!$this->isPermissionAllowed($project_key, 'edit_issue'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $labels = $request->input('labels');
        if (!isset($labels))
        {
            return $this->show($project_key, $id);
        }

        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->find($id);
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $updValues['labels'] = $labels;

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();
        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, null, [ 'labels' ]);
        // trigger event of issue edited
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
        // create the Labels for project
        if ($labels)
        {
            $this->createLabels($project_key, $labels);
        }

        return $this->show($project_key, $id);
    }

    /**
     * create the new labels for project.
     *
     * @param  string  $project_key
     * @param  array $labels
     * @return void
     */
    public function createLabels($project_key, $labels)
    {
        $created_labels = [];
        $project_labels = Labels::where('project_key', $project_key)
            ->whereIn('name', $labels)
            ->get();
        foreach ($project_labels as $label)
        {
            $created_labels[] = $label->name;
        }
        // get uncreated labels
        $new_labels = array_diff($labels, $created_labels);
        foreach ($new_labels as $label)
        {
            Labels::create([ 'project_key' => $project_key, 'name' => $label ]);
        }
        return true;
    }

    /**
     * check the required field
     *
     * @param  array $schema
     * @param  array $data
     * @param  string $mode
     * @return bool
     */
    public function requiredCheck($schema, $data, $mode='create')
    {
        foreach ($schema as $field)
        {
            if (isset($field['required']) && $field['required'])
            {
                if ($mode == 'update')
                {
                    if (isset($data[$field['key']]) && !$data[$field['key']] && $data[$field['key']] !== 0)
                    {
                        return false;
                    }
                }
                else 
                {
                    if (!isset($data[$field['key']]) || !$data[$field['key']] && $data[$field['key']] !== 0)
                    {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * check if the unix stamp
     *
     * @param  int $timestamp
     * @return bool
     */
    public function isTimestamp($timestamp) 
    {
        if(strtotime(date('Y-m-d H:i:s', $timestamp)) === $timestamp) 
        {
            return $timestamp;
        } 
        else 
        {
            return false;
        }
    }

    /**
     * get valid keys by schema
     *
     * @param  array $schema
     * @return array
     */
    public function getValidKeysBySchema($schema=[])
    {
        $valid_keys = array_merge(array_column($schema, 'key'), [ 'type', 'assignee', 'labels', 'parent_id', 'resolution', 'priority', 'progress', 'expect_start_time', 'expect_compete_time' ]);

        foreach ($schema as $field)
        {
            if ($field['type'] == 'MultiUser')
            {
                $valid_keys[] = $field['key'] . '_ids';
            }
            else if ($field['type'] == 'TimeTracking')
            {
                $valid_keys[] = $field['key'] . '_m';
            }
        }

        return $valid_keys;
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
        if (!$this->isPermissionAllowed($project_key, 'edit_issue') && !$this->isPermissionAllowed($project_key, 'exec_workflow'))
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

        if (!$this->requiredCheck($schema, $request->all(), 'update'))
        {
            throw new \UnexpectedValueException('the required field is empty.', -11121);
        }

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
                    throw new \UnexpectedValueException('the format of timetracking field is incorrect.', -11102);
                }

                $updValues[$field['key']] = $this->ttHandle($fieldValue);
                $updValues[$field['key'] . '_m'] = $this->ttHandleInM($updValues[$field['key']]);
            }
            else if ($field['type'] == 'DatePicker' || $field['type'] == 'DateTimePicker')
            {
                if ($this->isTimestamp($fieldValue) === false) 
                {
                    throw new \UnexpectedValueException('the format of datepicker field is incorrect.', -11122);
                }
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
                $user_ids = $fieldValue;
                $updValues[$field['key']] = [];
                $new_user_ids = [];
                foreach ($user_ids as $uid)
                {
                    $user_info = Sentinel::findById($uid);
                    if ($user_info)
                    {
                        array_push($updValues[$field['key']], [ 'id' => $uid, 'name' => $user_info->first_name, 'email' => $user_info->email ]);
                    }
                    $new_user_ids[] = $uid;
                }
                $updValues[$field['key'] . '_ids'] = $new_user_ids;
            }
        }

        $assignee_id = $request->input('assignee');
        if ($assignee_id)
        {
            if ((!isset($issue['assignee']) || (isset($issue['assignee']) && $assignee_id != $issue['assignee']['id'])) && !$this->isPermissionAllowed($project_key, 'assigned_issue', $assignee_id))
            {
                return Response()->json(['ecode' => -11118, 'emsg' => 'the assigned user has not assigned-issue permission.']);
            }

            $user_info = Sentinel::findById($assignee_id);
            if ($user_info)
            {
                $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                $updValues['assignee'] = $assignee;
            }
        }

        $valid_keys = $this->getValidKeysBySchema($schema);
        $updValues = $updValues + array_only($request->all(), $valid_keys);
        if (!$updValues)
        {
            return $this->show($project_key, $id);
        }

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();

        DB::collection($table)->where('_id', $id)->update($updValues);

        // add to histroy table
        $snap_id = Provider::snap2His($project_key, $id, $schema, array_keys(array_only($request->all(), $valid_keys)));
        // trigger event of issue edited
        Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
        // create the Labels for project
        if (isset($updValues['labels']) && $updValues['labels'])
        {
            $this->createLabels($project_key, $updValues['labels']);
        }

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
        $issue = DB::collection($table)
            ->where('_id', $id)
            ->where('del_flg', '<>', 1)
            ->first();
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        
        $ids = [ $id ];
        // delete all subtasks of this issue
        $subtasks = DB::collection('issue_' . $project_key)
            ->where('parent_id', $id)
            ->where('del_flg', '<>', 1)
            ->get();
        foreach ($subtasks as $subtask)
        {
            $sub_id = $subtask['_id']->__toString();
            DB::collection($table)->where('_id', $sub_id)->update([ 'del_flg' => 1 ]);

            // delete linked relation
            DB::collection('linked')->where('src', $sub_id)->orWhere('dest', $sub_id)->delete();

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
     * get the project filters.
     *
     * @param  string $project_key
     * @return array
     */
    public function getIssueFilters($project_key)
    {
        return Response()->json([ 'ecode' => 0, 'data' => Provider::getIssueFilters($project_key, $this->user->id) ]);
    }

    /**
     * save the custimized filter.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function saveIssueFilter(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11105);
        }

        if (UserIssueFilters::whereRaw([ 'name' => $name, 'user' => $this->user->id, 'project_key' => $project_key ])->exists())
        {
            throw new \UnexpectedValueException('filter name cannot be repeated', -11106);
        }

        $query = $request->input('query') ?: [];
        
        $res = UserIssueFilters::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->first();
        if ($res)
        {
            $filters = isset($res['filters']) ? $res['filters'] : [];
            foreach($filters as $filter)
            {
                if (isset($filter['name']) && $filter['name'] === $name)
                {
                    throw new \UnexpectedValueException('filter name cannot be repeated', -11106);
                }
            }
            array_push($filters, [ 'id' => md5(microtime()), 'name' => $name, 'query' => $query ]);
            $res->filters = $filters;
            $res->save();
        }
        else
        {
            $filters = Provider::getDefaultIssueFilters();
            foreach($filters as $filter)
            {
                if (isset($filter['name']) && $filter['name'] === $name)
                {
                    throw new \UnexpectedValueException('filter name cannot be repeated', -11106);
                }
            }
            array_push($filters, [ 'id' => md5(microtime()), 'name' => $name, 'query' => $query ]);
            UserIssueFilters::create([ 'project_key' => $project_key, 'user' => $this->user->id, 'filters' => $filters ]); 
        }
        return $this->getIssueFilters($project_key);
    }

    /**
     * reset the issue filters.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function resetIssueFilters(Request $request, $project_key)
    {
        UserIssueFilters::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->delete();

        return $this->getIssueFilters($project_key);
    }

    /**
     * get the default columns.
     *
     * @param  string $project_key
     * @return array
     */
    public function getDisplayColumns($project_key)
    {
        return Response()->json([ 'ecode' => 0, 'data' => Provider::getIssueDisplayColumns($project_key, $this->user->id) ]);
    }

    /**
     * reset the issue list diplay columns.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function resetDisplayColumns(Request $request, $project_key)
    {
        UserIssueListColumns::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->delete();

        $delete_from_project = $request->input('delete_from_project') ?: false;
        if ($delete_from_project && $this->isPermissionAllowed($project_key, 'manage_project'))
        {
            ProjectIssueListColumns::where('project_key', $project_key)->delete();
        }
 
        return $this->getDisplayColumns($project_key);
    }

    /**
     * set the issue list display columns.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function setDisplayColumns(Request $request, $project_key)
    {
        $column_keys = [];
        $new_columns = [];
        $columns = $request->input('columns') ?: [];
        foreach ($columns as $column)
        {
            if (!isset($column['key']))
            {
                continue;
            }

            if (in_array($column['key'], $column_keys))
            {
                continue;
            }
            $column_keys[] = $column['key'];
            $new_columns[] = array_only($column, [ 'key', 'width' ]);
        }

        $res = UserIssueListColumns::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->first();
        if ($res)
        {
            $res->columns = $new_columns;
            $res->column_keys = $column_keys;
            $res->save();
        }
        else
        {
            UserIssueListColumns::create([ 'project_key' => $project_key, 'user' => $this->user->id, 'column_keys' => $column_keys, 'columns' => $new_columns ]); 
        }

        $save_for_project = $request->input('save_for_project') ?: false;
        if ($save_for_project && $this->isPermissionAllowed($project_key, 'manage_project'))
        {
            $res = ProjectIssueListColumns::where('project_key', $project_key)->first();
            if ($res)
            {
                $res->columns = $new_columns;
                $res->column_keys = $column_keys;
                $res->save();
            }
            else
            {
                ProjectIssueListColumns::create([ 'project_key' => $project_key, 'column_keys' => $column_keys, 'columns' => $new_columns ]);
            }
        }

        return $this->getDisplayColumns($project_key);
    }

    /**
     * edit the mode filters.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function editFilters(Request $request, $project_key)
    {
        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            $old_filters = Provider::getDefaultIssueFilters();
            $res = UserIssueFilters::where('project_key', $project_key)
                ->where('user', $this->user->id)
                ->first();
            if ($res)
            {
                $old_filters = isset($res->filters) ? $res->filters : [];
            }
            
            $new_filters = [];
            foreach ($sequence as $id)
            {
                foreach ($old_filters as $filter)
                {
                    if ($filter['id'] === $id)
                    {
                        $new_filters[] = $filter;
                        break;
                    }
                }
            }
            if ($res)
            {
                $res->filters = $new_filters;
                $res->save();
            }
            else
            {
                UserIssueFilters::create([ 'project_key' => $project_key, 'user' => $this->user->id, 'filters' => $new_filters ]); 
            }
        }
        return $this->getIssueFilters($project_key);
    }

    /**
     * reset the issue list columns.
     *
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function resetColumns(Request $request, $project_key)
    {
        UserIssueListColumns::where('project_key', $project_key)
            ->where('user', $this->user->id)
            ->delete();

        return $this->getColumns($project_key);
    }

    /**
     * get the history records.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function getHistory(Request $request, $project_key, $id)
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

        $sort = ($request->input('sort') === 'asc') ? 'asc' : 'desc';
        if ($sort === 'desc')
        {
            $changedRecords = array_reverse($changedRecords);
        }

        return Response()->json([ 'ecode' => 0, 'data' => $changedRecords, 'options' => [ 'current_time' => time() ] ]);
    }

    /**
     * workflow action.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @param  string  $action_id
     * @return \Illuminate\Http\Response
     */
    public function doAction(Request $request, $project_key, $id, $workflow_id)
    {
        $action_id = $request->input('action_id');
        if (!$action_id)
        {
            throw new Exception('the executed action has error.', -11115);
        }

        try {
            $entry = new Workflow($workflow_id);
            $entry->doAction($action_id, [ 'project_key' => $project_key, 'issue_id' => $id, 'caller' => $this->user->id ] + array_only($request->all(), [ 'comments' ]));
        } catch (Exception $e) {
          throw new Exception('the executed action has error.', -11115);
        }
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
        $updValues = [];

        $assignee_id = $request->input('assignee');
        if (isset($assignee_id))
        {
            if ($assignee_id)
            {
                if (!$this->isPermissionAllowed($project_key, 'assigned_issue', $assignee_id))
                {
                    return Response()->json(['ecode' => -11118, 'emsg' => 'the assigned user has not assigned-issue permission.']);
                }

                $user_info = Sentinel::findById($assignee_id);
                if ($user_info)
                {
                    $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                    $updValues['assignee'] = $assignee;
                }
            }
            else
            {
                throw new \UnexpectedValueException('the issue assignee cannot be empty.', -11104);
            }
        }

        $resolution = $request->input('resolution');
        if (isset($resolution) && $resolution)
        {
            $updValues['resolution'] = $resolution;
        }

        $issue = DB::collection('issue_' . $project_key)->where('_id', $id)->first();
        if (!$issue)
        {
            throw new \UnexpectedValueException('the issue does not exist or is not in the project.', -11103);
        }

        // workflow initialize
        $workflow = $this->initializeWorkflow($issue['type']);
        $updValues = $updValues + $workflow;

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
        if (!$title)
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

        $valid_keys = $this->getValidKeysBySchema($schema);
        $insValues = array_only($src_issue, $valid_keys);

        $insValues['title'] = $title;
        // get reporter(creator)
        $insValues['reporter'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $assignee_id = $request->input('assignee');
        if (isset($assignee_id))
        {
            if ($assignee_id)
            {
                if (!$this->isPermissionAllowed($project_key, 'assigned_issue', $assignee_id))
                {
                    return Response()->json(['ecode' => -11118, 'emsg' => 'the assigned user has not assigned-issue permission.']);
                }

                $user_info = Sentinel::findById($assignee_id);
                if ($user_info)
                {
                    $assignee = [ 'id' => $assignee_id, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                    $insValues['assignee'] = $assignee;
                }
            }
            else
            {
                throw new \UnexpectedValueException('the issue assignee cannot be empty.', -11104);
            }
        }

        $resolution = $request->input('resolution');
        if (isset($resolution) && $resolution)
        {
            $insValues['resolution'] = $resolution;
        }

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
    public function release(Request $request, $project_key) 
    {
        $ids = $request->input('ids'); 
        if (!$ids)
        {
            throw new \UnexpectedValueException('the released issues cannot be empty.', -11132);
        }

        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the released version cannot be empty.', -11131);
        }

        $isExisted = Version::where('project_key', $project_key)
            ->where('name', $name)
            ->exists();
        if ($isExisted)
        {
            throw new \UnexpectedValueException('the released version has been existed.', -11133);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $version = Version::create([ 'project_key' => $project_key, 'user' => $user, 'status' => 'released', 'released_time' => time() ] + $request->all());

        foreach ($ids as $id)
        {
            DB::collection('issue_' . $project_key)->where('_id', $id)->update([ 'resolve_version' => $version->id ]);
            // add to histroy table
            $snap_id = Provider::snap2His($project_key, $id, null, [ 'resolve_version' ]);
            // trigger event of issue moved
            Event::fire(new IssueEvent($project_key, $id, $user, [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ] ));
        }

        $isSendMsg = $request->input('isSendMsg') && true;
        Event::fire(new VersionEvent($project_key, $user, [ 'event_key' => 'create_release_version', 'isSendMsg' => $isSendMsg, 'data' => [ 'released_issues' => $ids, 'release_version' => $version->toArray() ] ]));

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
     * get issue link relations.
     *
     * @return array
     */
    function getLinkRelations()
    {
        $relations = [
          [ 'id' => 'blocks', 'out' => 'blocks', 'in' => 'is blocked by' ],
          [ 'id' => 'clones', 'out' => 'clones', 'in' => 'is cloned by' ],
          [ 'id' => 'duplicates', 'out' => 'duplicates', 'in' => 'is duplicated by' ],
          [ 'id' => 'relates', 'out' => 'relates to', 'in' => 'relates to' ],
        ];
        return $relations;
    }

    /**
     * classify issues by parent_id.
     *
     * @param  array  $issues
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
     * @param  array  $issues
     * @return array
     */
    public function addAvatar(&$issues)
    {
        $cache_avatars = [];
        foreach ($issues as $key => $issue)
        {
            if (!isset($issue['assignee']) || !isset($issue['assignee']['id']))
            {
                continue;
            }
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
     * @param  array  $classifiedissues
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
     * @param  string $project_key
     * @param  array  $issues
     * @param  string $from
     * @param  string $from_board_id
     * @param  bool   $isUpdRank
     * @return array 
     */
    public function arrangeIssues($project_key, $issues, $from, $from_board_id, $isUpdRank=false)
    {
        if ($from === 'his_sprint')
        {
            $this->addAvatar($issues);
            return $issues;
        }

        // classify the issues
        $classified_issues = $this->classifyIssues($issues);

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

            if ($from === 'active_sprint')
            {
                $issues = $this->sprintFilter($project_key, $issues);
            }

            $this->addAvatar($issues);
            return $issues;
        }
 
        $sub2parent_map = []; 
        foreach ($issues as $issue)
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
            $parentInd = 0;
            foreach ($classified_issues[$no] as $sk => $si)
            {
                if ($si['no'] === $no)
                {
                    $parentInd = $sk;
                    break;
                }
            }
            if ($parentInd > 0)
            {
                $pi = array_splice($classified_issues[$no], $parentInd, 1);
                array_unshift($classified_issues[$no], array_pop($pi));
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

        if ($from === 'active_sprint')
        {
            $issues = $this->sprintFilter($project_key, $issues);
        }

        $this->addAvatar($issues);
        return $issues;
    }

    /**
     * sprint filter for issues
     *
     * @param  string $project_key
     * @param  array  $issues
     * @return array
     */
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

    /**
     * get some options for export 
     *
     * @param  string $project_key
     * @return array
     */
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
            if (isset($field->optionValues))
            {
                $tmp['optionValues'] = $field->optionValues;
            }
            $fields[$field->key] = $tmp;
        }

        $fields['no'] = [ 'name' => 'NO', 'type' => 'Number' ];
        $fields['type'] = [ 'name' => '类型', 'type' => 'Select' ];
        $fields['state'] = [ 'name' => '状态', 'type' => 'Select' ];
        $fields['created_at'] = [ 'name' => '创建时间', 'type' => 'DateTimePicker' ];
        $fields['updated_at'] = [ 'name' => '更新时间', 'type' => 'DateTimePicker' ];
        $fields['resolved_at'] = [ 'name' => '解决时间', 'type' => 'DateTimePicker' ];
        $fields['closed_at'] = [ 'name' => '关闭时间', 'type' => 'DateTimePicker' ];
        $fields['reporter'] = [ 'name' => '报告者', 'type' => '' ];
        $fields['resolver'] = [ 'name' => '解决者', 'type' => '' ];
        $fields['closer'] = [ 'name' => '关闭者', 'type' => '' ];
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

    /**
     * export xls for issue list
     *
     * @param  string $project_key
     * @return void
     */
    public function imports(Request $request, $project_key)
    {
        set_time_limit(0);

        if (!($fid = $request->input('fid')))
        {
            throw new \UnexpectedValueException('导入文件ID不能为空。', -11140);
        }

        $pattern = $request->input('pattern');
        if (!isset($pattern))
        {
            $pattern = '1';
        }

        $file = config('filesystems.disks.local.root', '/tmp') . '/' . substr($fid, 0, 2) . '/' . $fid;
        if (!file_exists($file))
        {
            throw new \UnexpectedValueException('获取导入文件失败。', -11141);
        }

        $err_msgs = [];
        $fatal_err_msgs = [];
        Excel::load($file, function($reader) use($project_key, $pattern, &$err_msgs, &$fatal_err_msgs) {
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            if (!$data)
            {
                $fatal_err_msgs = $err_msgs = '文件内容不能为空。';
                return;
            }

            $new_fields = [];
            $fields = Provider::getFieldList($project_key);
            foreach($fields as $field)
            {
                if ($field->type !== 'File')
                { 
                    $new_fields[$field->key] = $field->name;
                }
            }
            $new_fields['type'] = '类型';
            $new_fields['state'] = '状态';
            $new_fields['parent'] = '父级任务';
            $new_fields['reporter'] = '报告者';
            $new_fields['created_at'] = '创建时间';
            $new_fields['updated_at'] = '更新时间';
            $new_fields['resolver'] = '解决者';
            $new_fields['resolved_at'] = '解决时间';
            $new_fields['closer'] = '关闭者';
            $new_fields['closed_at'] = '关闭时间';
            $fields = $new_fields;

            // arrange the excel data
            $data = $this->arrangeExcel($data, $fields);
            foreach ($data as $val)
            {
                if (!isset($val['title']) && !isset($val['type']))
                {
                    $fatal_err_msgs = $err_msgs = '主题列和类型列没找到。';
                }
                else if (!isset($val['title']))
                {
                    $fatal_err_msgs = $err_msgs = '主题列没找到。';
                }
                else if (!isset($val['type']))
                {
                    $fatal_err_msgs = $err_msgs = '类型列没找到。';
                }
                else if (!$val['title'])
                {
                    $fatal_err_msgs = $err_msgs = '主题列不能有空值。';
                }

                if ($err_msgs)
                {
                    return;
                }
            }

            // get the type schema
            $new_types = [];
            $standard_type_ids = [];
            $types = Provider::getTypeList($project_key);
            foreach ($types as $type)
            {
                $tmp = [];
                $tmp['id'] = $type->id;
                $tmp['name'] = $type->name;
                $tmp['type'] = $type->type ?: 'standard';
                $tmp['workflow'] = $type->workflow;
                $tmp['schema'] = Provider::getSchemaByType($type->id);
                $new_types[$type->name] = $tmp;
                if ($tmp['type'] == 'standard')
                {
                    $standard_type_ids[] = $tmp['id'];
                }
            }
            $types = $new_types;

            // get the state option
            $new_priorities = [];
            $priorities = Provider::getPriorityOptions($project_key);
            foreach($priorities as $priority)
            {
                $new_priorities[$priority['name']] = $priority['_id'];
            }
            $priorities = $new_priorities;

            // get the state option
            $new_states = [];
            $states = Provider::getStateOptions($project_key);
            foreach($states as $state)
            {
                $new_states[$state['name']] = $state['_id'];
            }
            $states = $new_states;

            // get the state option
            $new_resolutions = [];
            $resolutions = Provider::getResolutionOptions($project_key);
            foreach($resolutions as $resolution)
            {
                $new_resolutions[$resolution['name']] = $resolution['_id'];
            }
            $resolutions = $new_resolutions;

            // initialize the error msg
            foreach ($data as $val)
            {
                $err_msgs[$val['title']] = [];
                $fatal_err_msgs[$val['title']] = [];
            }

            $standard_titles = [];
            $standard_issues = [];
            $subtask_issues = [];

            foreach ($data as $value)
            {
                $issue = [];
                $cur_title = $issue['title'] = $value['title'];

                if (!$value['type'])
                {
                    $fatal_err_msgs[$cur_title][] = $err_msgs[$cur_title][] = '类型列不能有空值。';
                    continue;
                }
                else if (!isset($types[$value['type']]))
                {
                    $fatal_err_msgs[$cur_title][] = $err_msgs[$cur_title][] = '类型列值匹配失败。';
                    continue;
                }
                else 
                {
                    $issue['type'] = $types[$value['type']]['id'];
                }

                if ($types[$value['type']]['type'] === 'subtask' && (!isset($value['parent']) || !$value['parent']))
                {
                    $fatal_err_msgs[$cur_title][] = $err_msgs[$cur_title][] = '父级任务列不能为空。';
                }
                else
                {
                    $issue['parent'] = $value['parent'];
                }

                if (isset($value['priority']) && $value['priority'])
                {
                    if (!isset($priorities[$value['priority']]) || !$priorities[$value['priority']])
                    {
                        $err_msgs[$cur_title][] = '优先级列值匹配失败。';
                    }
                    else
                    {
                        $issue['priority'] = $priorities[$value['priority']];
                    }
                }

                if (isset($value['state']) && $value['state'])
                {
                    if (!isset($states[$value['state']]) || !$states[$value['state']])
                    {
                        $err_msgs[$cur_title][] = '状态列值匹配失败。';
                    }
                    else
                    {
                        $issue['state'] = $states[$value['state']];
                        $workflow = $types[$value['type']]['workflow'];
                        if (!in_array($issue['state'], $workflow['state_ids']))
                        {
                            $err_msgs[$cur_title][] = '状态列值不在相应流程里。';
                        }
                    }
                }

                if (isset($value['resolution']) && $value['resolution'])
                {
                    if (!isset($resolutions[$value['resolution']]) || !$resolutions[$value['resolution']])
                    {
                        $err_msgs[$cur_title][] = '解决结果列值匹配失败。';
                    }
                    else
                    {
                        $issue['resolution'] = $resolutions[$value['resolution']];
                    }
                }

                $user_relate_fields = [ 'assignee' => '经办人', 'reporter' => '报告者', 'resolver' => '解决者', 'closer' => '关闭时间' ];
                foreach ($user_relate_fields as $uk => $uv)
                {
                    if (isset($value[$uk]) && $value[$uk])
                    {
                        $tmp_user = EloquentUser::where('first_name', $value[$uk])->first();
                        if (!$tmp_user)
                        {
                            $err_msgs[$cur_title][] = $uv . '列用户不存在。';
                        }
                        else
                        {
                            $issue[$uk] = [ 'id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email ];
                            if ($uk == 'resolver')
                            {
                                $issue['his_resolvers'] = [ $tmp_user->id ];
                            }
                        }
                    }
                }

                $time_relate_fields = [ 'created_at' => '创建时间', 'resolved_at' => '解决时间', 'closed_at' => '关闭时间', 'updated_at' => '更新时间' ];
                foreach ($time_relate_fields as $tk => $tv)
                {
                    if (isset($value[$tk]) && $value[$tk])
                    {
                        $stamptime = strtotime($value[$tk]);
                        if ($stamptime === false)
                        {
                            $err_msgs[$cur_title][] = $tv . '列值格式错误。';
                        }
                        else
                        {
                            $issue[$tk] = $stamptime;
                        }
                    }
                }

                $schema = $types[$value['type']]['schema'];
                foreach ($schema as $field)
                {
                    if (isset($field['required']) && $field['required'] && (!isset($value[$field['key']]) || !$value[$field['key']]))
                    {
                        $err_msgs[$cur_title][] = $fields[$field['key']] . '列值不能为空。';
                        continue;
                    }

                    if (isset($value[$field['key']]) && $value[$field['key']])
                    {
                        $field_key = $field['key'];
                        $field_value = $value[$field['key']];
                    }
                    else
                    {
                        continue;
                    }

                    if (in_array($field_key, [ 'priority', 'resolution', 'assignee' ]))
                    {
                        continue;
                    }

                    //if ($field_key === 'Sprint')
                    //{
                    //    $sprints = explode(',', $field_value);
                    //    $new_sprints = [];
                    //    foreach ($sprints as $s)
                    //    {
                    //        $new_sprints[] = intval($s);
                    //    }
                    //    $issue['sprints'] = $new_sprints;
                    //}
                    if ($field_key == 'labels')
                    {
                        $issue['labels'] = [];
                        foreach (explode(',', $field_value) as $val)
                        {
                            if (trim($val))
                            {
                                $issue['labels'][] = trim($val); 
                            }
                        }
                        $issue['labels'] = array_values(array_unique($issue['labels']));
                    }
                    else if ($field['type'] === 'SingleUser' || $field_key === 'assignee')
                    {
                        $tmp_user = EloquentUser::where('first_name', $field_value)->first();
                        if (!$tmp_user)
                        {
                            $err_msgs[$cur_title][] = $fields[$field_key] . '列用户不存在。';
                        }
                        else
                        {
                            $issue[$field_key] = [ 'id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email ];
                        }
                    }
                    else if ($field['type'] === 'MultiUser')
                    {
                        $issue[$field_key] = [];
                        $issue[$field_key . '_ids'] = [];
                        foreach(explode(',', $field_value) as $val)
                        {
                            if (!trim($val))
                            {
                                continue;
                            }

                            $tmp_user = EloquentUser::where('first_name', trim($val))->first();
                            if (!$tmp_user)
                            {
                                $err_msgs[$cur_title][] = $fields[$field_key] . '列用户不存在。';
                            }
                            else if (!in_array($tmp_user->id, $issue[$field_key . '_ids']))
                            {
                                $issue[$field_key][] = [ 'id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email ];
                                $issue[$field_key . '_ids'][] = $tmp_user->id;
                            }
                        }
                    }
                    else if (in_array($field['type'], [ 'Select', 'RadioGroup', 'SingleVersion' ]))
                    {
                        foreach ($field['optionValues'] as $val)
                        {
                            if ($val['name'] === $field_value)
                            {
                                $issue[$field_key] = $val['id'];
                                break;
                            }
                        }
                        if (!isset($issue[$field_key]))
                        {
                            $err_msgs[$cur_title][] = $fields[$field_key] . '列值匹配失败。';
                        }
                    }
                    else if (in_array($field['type'], [ 'MultiSelect', 'CheckboxGroup', 'MultiVersion' ]))
                    {
                        $issue[$field_key] = [];
                        foreach (explode(',', $field_value) as $val)
                        {
                            $val = trim($val);
                            if (!$val)
                            {
                                continue;
                            }

                            $isMatched = false;
                            foreach ($field['optionValues'] as $val2)
                            {
                                if ($val2['name'] === $val)
                                {
                                    $issue[$field_key][] = $val2['id'];
                                    $isMatched = true;
                                    break;
                                }
                            }
                            if (!$isMatched)
                            {
                                $err_msgs[$cur_title][] = $fields[$field_key] . '列值匹配失败。';
                            }
                        }
                        $issue[$field_key] = array_values(array_unique($issue[$field_key]));
                    }
                    else if (in_array($field['type'], [ 'DatePicker', 'DatetimePicker' ]))
                    {
                        $stamptime = strtotime($field_value);
                        if ($stamptime === false)
                        {
                            $err_msgs[$cur_title][] = $fields[$field_key] . '列值格式错误。';
                        }
                        else
                        {
                            $issue[$field_key] = $stamptime;
                        }
                    }
                    else if ($field['type'] === 'TimeTracking')
                    {
                        if (!$this->ttCheck($field_value))
                        {
                            $err_msgs[$cur_title][] = $fields[$field_key] . '列值格式错误。';
                        }
                        else
                        {
                            $issue[$field_key] = $this->ttHandle($field_value);
                            $issue[$field_key . '_m'] = $this->ttHandleInM($issue[$field_key]);
                        }
                    }
                    else if ($field['type'] === 'Number')
                    {
                        $issue[$field_key] = floatval($field_value);
                    }
                    else
                    {
                        $issue[$field_key] = $field_value;
                    }
                }

                if ($types[$value['type']]['type'] === 'subtask')
                {
                    $subtask_issues[] = $issue;
                }
                else
                {
                    $standard_titles[] = $issue['title'];
                    if (isset($issue['parent']))
                    {
                        unset($issue['parent']);
                    }
                    $standard_issues[] = $issue;
                }
            }

            $new_subtask_issues = [];
            foreach ($standard_titles as $title)
            {
                $new_subtask_issues[$title] = [];
            }

            foreach ($subtask_issues as $issue)
            {
                $parent_issues = array_filter($standard_issues, function($v) use ($issue) { return $v['title'] === $issue['parent']; });
                if (count($parent_issues) > 1)
                {
                    $fatal_err_msgs[$issue['title']][] = $err_msgs[$issue['title']][] = '找到多个父级任务。';
                }
                else if (count($parent_issues) == 1)
                {
                    $parent_issue = array_pop($parent_issues);
                    if (isset($issue['parent']))
                    {
                        unset($issue['parent']);
                    }
                    $new_subtask_issues[$parent_issue['title']][] = $issue;
                }
                else
                {
                    $parent_issues = DB::table('issue_' . $project_key)
                        ->where('title', $issue['parent'])
                        ->whereIn('type', $standard_type_ids)
                        ->where('del_flg', '<>', 1)
                        ->get();
                    if (count($parent_issues) > 1)
                    {
                        $fatal_err_msgs[$issue['title']][] = $err_msgs[$issue['title']][] = '找到多个父级任务。';
                    }
                    else if (count($parent_issues) == 1)
                    {
                        $parent_issue = array_pop($parent_issues);
                        if (isset($issue['parent']))
                        {
                            unset($issue['parent']);
                        }
                        $new_subtask_issues[] = $issue + [ 'parent_id' => $parent_issue['_id']->__toString() ];
                    }
                    else
                    {
                        $fatal_err_msgs[$issue['title']][] = $err_msgs[$issue['title']][] = '父级任务不存在。';
                    }
                }
            }
            $subtask_issues = array_filter($new_subtask_issues);

            $err_msgs = array_filter($err_msgs);
            $fatal_err_msgs = array_filter($fatal_err_msgs);
            if ($pattern == '2')
            {
                if ($fatal_err_msgs)
                {
                    return;
                }
            }
            else if ($err_msgs)
            {
                return;
            }


            $new_types = [];
            foreach($types as $type)
            {
                $new_types[$type['id']] = $type;
            }
            $types = $new_types;

            foreach ($subtask_issues as $issue)
            {
                if (isset($issue['parent_id']) && $issue['parent_id'])
                {
                    $this->importIssue($project_key, $issue, $types[$issue['type']]['schema'], $types[$issue['type']]['workflow']);
                }
            }

            foreach ($standard_issues as $issue)
            {
                $id = $this->importIssue($project_key, $issue, $types[$issue['type']]['schema'], $types[$issue['type']]['workflow']);
                if (!isset($subtask_issues[$issue['title']]) || !$subtask_issues[$issue['title']])
                {
                    continue;
                }
                foreach ($subtask_issues[$issue['title']] as $sub_issue)
                {
                    $sub_issue['parent_id'] = $id;
                    $this->importIssue($project_key, $sub_issue, $types[$sub_issue['type']]['schema'], $types[$sub_issue['type']]['workflow']);
                }
            }
        });


        $emsgs = '';
        if ($pattern == '2')
        {
            $emsgs = array_filter($fatal_err_msgs);
        }
        else
        {
            $emsgs = array_filter($err_msgs);
        }

        if ($emsgs)
        {
            return Response()->json([ 'ecode' => -11146, 'emsg' => $emsgs ]);
        }
        else
        {
            return Response()->json([ 'ecode' => 0, 'emsg' => '' ]);
        }
    }

    /**
     * import the issue into the project 
     *
     * @param  string $project_key
     * @param  array $data
     * @param  array $schema
     * @return string id 
     */
    public function importIssue($project_key, $data, $schema, $workflow)
    {
        $table = 'issue_' . $project_key;

        $insValues = $data;
        if (!isset($insValues['resolution']) || !$insValues['resolution'])
        {
            $insValues['resolution'] = 'Unresolved';
        }

        $max_no = DB::collection($table)->count() + 1;
        $insValues['no'] = $max_no;

        if (!isset($insValues['assignee']) || !$insValues['assignee'])
        {
            $insValues['assignee'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        }

        // get reporter(creator)
        if (!isset($insValues['reporter']) || !$insValues['reporter'])
        {
            $insValues['reporter'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        }
        if (!isset($insValues['created_at']) || !$insValues['created_at'])
        {
            $insValues['created_at'] = time();
        }

        if (!isset($data['state']) || !$data['state'])
        {
            $wf = $this->initializeWorkflow($data['type']);
            $insValues += $wf;
        }
        else if (in_array($data['state'], $workflow->state_ids ?: []))
        {
            $wf = $this->initializeWorkflowForImport($workflow, $data['state']);
            $insValues += $wf;
        }

        $id = DB::collection('issue_' . $project_key)->insertGetId($insValues);
        $id = $id->__toString();

        // add to histroy table
        Provider::snap2His($project_key, $id, $schema);
        // trigger event of issue created
        Event::fire(new IssueEvent($project_key, $id, $insValues['reporter'], [ 'event_key' => 'create_issue' ]));

        if (isset($insValues['labels']) && $insValues['labels'])
        {
            $this->createLabels($project_key, $insValues['labels']);
        }

        return $id;
    }

    /**
     * initialize the workflow for the issue import.
     *
     * @param  object  $wf_definition
     * @param  string  $state
     * @return array
     */
    public function initializeWorkflowForImport($wf_definition, $state)
    {
        // create and start workflow instacne
        $wf_entry = Workflow::createInstance($wf_definition->id);

        $wf_contents = $wf_definition->contents ?: [];
        $steps = isset($wf_contents['steps']) && $wf_contents['steps'] ? $wf_contents['steps'] : [];

        $fake_step = [];
        foreach($steps as $step)
        {
            if (isset($step['state']) && $step['state'] == $state)
            {
                $fake_step = $step;
                break;
            }
        }
        if (!$fake_step)
        {
            return [];
        }

        $caller = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $wf_entry->fakeNewCurrentStep($fake_step, $caller);

        $ret['entry_id'] = $wf_entry->getEntryId();
        $ret['definition_id'] = $wf_definition->id;

        return $ret;
    }

    /**
     * export xls for issue list 
     *
     * @param  string $project_key
     * @param  array $export_fields
     * @param  array $issues
     * @return void
     */
    public function export($project_key, $export_fields, $issues) 
    {
        set_time_limit(0);

        $options = $this->getOptionsForExport($project_key);
        foreach ($options as $key => $val)
        {
            $$key = $val;
        }

        foreach ($export_fields as $key => $field)
        {
            if (!array_key_exists($field, $fields))
            {
                unset($export_fields[$key]);
            }
        }
        $export_fields = array_values($export_fields);

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

                if (in_array($fk, [ 'assignee', 'reporter', 'closer', 'resolver' ]))
                {
                    $tmp[] = isset($issue[$fk]['name']) ? $issue[$fk]['name'] : '';
                }
                else if ($fk == 'module')
                {
                    $new_modules = [];
                    $module_ids = [];
                    if (is_array($issue[$fk]))
                    {
                        $module_ids = $issue[$fk];
                    }
                    else
                    {
                        $module_ids = explode(',', $issue[$fk]);
                    }
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
                else if ($fk == 'labels')
                {
                    $tmp[] = implode(',', $issue[$fk]);
                }
                else if ($fk == 'progress')
                {
                    $tmp[] = $issue[$fk] . '%';
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
                        $version_ids = [];
                        if (is_array($issue[$fk]))
                        {
                            $version_ids = $issue[$fk];
                        }
                        else
                        {
                            $version_ids = explode(',', $issue[$fk]);
                        }
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
                        if (isset($fields[$fk]['optionValues']) && $fields[$fk]['optionValues'])
                        {
                            $tmpOptions = [];
                            foreach ($fields[$fk]['optionValues'] as $ov)
                            {
                                $tmpOptions[$ov['id']] = $ov['name'];
                            }
                            $ov_ids = [];
                            if (is_array($issue[$fk]))
                            {
                                $ov_ids = $issue[$fk]; 
                            }
                            else
                            {
                                $ov_ids = explode(',', $issue[$fk]); 
                            }
                            $ov_names = [];
                            foreach ($ov_ids as $ovid)
                            {
                                $ov_names[] = isset($tmpOptions[$ovid]) ? $tmpOptions[$ovid] : ''; 
                            }
                            $tmp[] = implode(',', array_filter($ov_names));
                        }
                        else
                        {
                            $tmp[] = (string)$issue[$fk];
                        }
                    }
                }
                else
                {
                    $tmp[] = (string)$issue[$fk];
                }
            }
            $new_issues[] = $tmp;
        }

        $file_name = $project_key . '-issues';
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

    /**
     * batch handle the issue
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $project_key
     * @return \Illuminate\Http\Response
     */
    public function batchHandle(Request $request, $project_key)
    {
        $method = $request->input('method');
        if ($method == 'update')
        {
            $data = $request->input('data');
            if (!$data || !isset($data['ids']) || !$data['ids'] || !is_array($data['ids']) || !isset($data['values']) || !$data['values'] || !is_array($data['values']))
            {
                throw new \UnexpectedValueException('the batch params has errors.', -11124);
            }
            return $this->batchUpdate($project_key, $data['ids'], $data['values']);
        }
        else if ($method == 'delete')
        {
            $data = $request->input('data');
            if (!$data || !isset($data['ids']) || !$data['ids'] || !is_array($data['ids']))
            {
                throw new \UnexpectedValueException('the batch params has errors.', -11124);
            }
            return $this->batchDelete($project_key, $data['ids']);
        }
        else
        {
            throw new \UnexpectedValueException('the batch method has errors.', -11125);
        }
    }

    /**
     * batch update the issue
     *
     * @param  string $project_key
     * @param  array $ids
     * @return \Illuminate\Http\Response
     */
    public function batchDelete($project_key, $ids)
    {
        if (!$this->isPermissionAllowed($project_key, 'delete_issue'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $table = 'issue_' . $project_key;
        foreach ($ids as $id)
        {
            $issue = DB::collection($table)
                ->where('_id', $id)
                ->where('del_flg', '<>', 1)
                ->first();
            if (!$issue)
            {
                continue;
            }

            // delete all subtasks of this issue
            $subtasks = DB::collection('issue_' . $project_key)
                ->where('parent_id', $id)
                ->where('del_flg', '<>', 1)
                ->get();
            foreach ($subtasks as $subtask)
            {
                $sub_id = $subtask['_id']->__toString();
                DB::collection($table)->where('_id', $sub_id)->update([ 'del_flg' => 1 ]);

                // delete linked relation
                DB::collection('linked')->where('src', $sub_id)->orWhere('dest', $sub_id)->delete();

                Event::fire(new IssueEvent($project_key, $sub_id, $user, [ 'event_key' => 'del_issue' ]));
            }

            // delete linked relation
            DB::collection('linked')->where('src', $id)->orWhere('dest', $id)->delete();

            // delete this issue
            DB::collection($table)->where('_id', $id)->update([ 'del_flg' => 1 ]);

            // trigger event of issue deleted 
            Event::fire(new IssueEvent($project_key, $id, $user, [ 'event_key' => 'del_issue' ]));
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $ids ] ]);
    }

    /**
     * batch update the issue
     *
     * @param  string $project_key
     * @param  array $ids
     * @param  array $values
     * @return \Illuminate\Http\Response
     */
    public function batchUpdate($project_key, $ids, $values)
    {
        if (!$this->isPermissionAllowed($project_key, 'edit_issue'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $schemas = [];

        $updValues = [];
        if (isset($values['type']))
        {
            if (!$values['type'])
            {
                throw new \UnexpectedValueException('the issue type can not be empty.', -11100);
            }
        
            if (!($schemas[$values['type']] = Provider::getSchemaByType($values['type'])))
            {
                throw new \UnexpectedValueException('the schema of the type is not existed.', -11101);
            }

            $updValues['type'] = $values['type'];
        }

        $new_fields = [];
        $fields = Provider::getFieldList($project_key);
        foreach($fields as $field)
        {
            $new_fields[$field->key] = $field;
        }
        $fields = $new_fields;

        foreach ($values as $key => $val)
        {
            if (!isset($fields[$key]) || $fields[$key]->type == 'File')
            {
                continue;
            }

            $field = $fields[$key];

            if ($field->type == 'DateTimePicker' || $field->type == 'DatePicker')
            {
                if ($this->isTimestamp($val))
                {
                    throw new \UnexpectedValueException('the format of datepicker field is incorrect.', -11122);
                }
                $updValues[$key] = $val;
            }
            else if ($field->type == 'TimeTracking')
            {
                if (!$this->ttCheck($val))
                {
                    throw new \UnexpectedValueException('the format of timetracking field is incorrect.', -11102);
                }
                $updValues[$key] = $this->ttHandle($val);
                $updValues[$key . '_m'] = $this->ttHandleInM($updValues[$key]);
            }
            else if ($key == 'assignee' || $field->type == 'SingleUser')
            {
                $user_info = Sentinel::findById($val);
                if ($user_info)
                {
                    $updValues[$key] = [ 'id' => $val, 'name' => $user_info->first_name, 'email' => $user_info->email ];
                }
            }
            else if ($field->type == 'MultiUser')
            {
                $user_ids = $val;
                $updValues[$key] = [];
                $new_user_ids = [];
                foreach ($user_ids as $uid)
                {
                    $user_info = Sentinel::findById($uid);
                    if ($user_info)
                    {
                        array_push($updValues[$key], [ 'id' => $uid, 'name' => $user_info->first_name, 'email' => $user_info->email ]);
                    }
                    $new_user_ids[] = $uid;
                }
                $updValues[$key . '_ids'] = $new_user_ids;
            }
            else 
            {
                $updValues[$key] = $val;
            }
        }

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();

        $table = 'issue_' . $project_key;
        foreach ($ids as $id)
        {
            $issue = DB::collection($table)->find($id);
            if (!$issue)
            {
                continue;
            }

            $schema = [];
            $type = isset($values['type']) ? $values['type'] : $issue['type'];
            if (!isset($schemas[$type]))
            {
                if (!($schemas[$type] = $schema = Provider::getSchemaByType($type)))
                {
                    continue;
                }
            }
            else 
            {
                $schema = $schemas[$type];
            }

            $valid_keys = $this->getValidKeysBySchema($schema);
            if (!array_only($updValues, $valid_keys))
            {
                continue;
            }

            DB::collection($table)->where('_id', $id)->update(array_only($updValues, $valid_keys));

            // add to histroy table
            $snap_id = Provider::snap2His($project_key, $id, $schema, array_keys(array_only($values, $valid_keys)));

            // trigger event of issue edited
            Event::fire(new IssueEvent($project_key, $id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
        }

        // create the Labels for project
        if (isset($updValues['labels']) && $updValues['labels'])
        {
            $this->createLabels($project_key, $updValues['labels']);
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $ids ] ]); 
    }
}
