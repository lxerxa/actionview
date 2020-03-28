<?php
namespace App\Project;

use App\Customization\Eloquent\State;
use App\Customization\Eloquent\StateProperty;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\ResolutionProperty;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\PriorityProperty;
use App\Customization\Eloquent\Type;
use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use App\Customization\Eloquent\Events;
use App\Acl\Eloquent\Role;
use App\Acl\Eloquent\Group;
use App\Acl\Acl;
use App\Workflow\Eloquent\Definition;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\UserGroupProject;
use App\Project\Eloquent\File;
use App\Project\Eloquent\Version;
use App\Project\Eloquent\Module;
use App\Project\Eloquent\Epic;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\Labels;
use App\Project\Eloquent\UserIssueFilters;
use App\Project\Eloquent\UserIssueListColumns;
use App\Project\Eloquent\ProjectIssueListColumns;

use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel;
use MongoDB\BSON\ObjectID;
use DB;

class Provider {

    /**
     * get project principal.
     *
     * @param string $project_key
     * @return array 
     */
    public static function getProjectPrincipal($project_key)
    {
        $project = Project::where('key', $project_key)->first()->toArray();
        return $project && isset($project['principal']) ? $project['principal'] : [];
    }

    /**
     * get the project default filters.
     *
     * @return array
     */
    public static function getDefaultIssueFilters()
    {
        return [
            [ 'id' => 'all', 'name' => '全部问题', 'query' => [] ],
            [ 'id' => 'unresolved', 'name' => '未解决的', 'query' => [ 'resolution' => 'Unresolved' ] ],
            [ 'id' => 'assigned_to_me', 'name' => '分配给我的', 'query' => [ 'assignee' => 'me', 'resolution' => 'Unresolved' ] ],
            [ 'id' => 'watched', 'name' => '我关注的', 'query' => [ 'watcher' => 'me' ] ],
            [ 'id' => 'reported', 'name' => '我报告的', 'query' => [ 'reporter' => 'me' ] ],
            [ 'id' => 'recent_created', 'name' => '最近增加的', 'query' => [ 'created_at' => '2w' ] ],
            [ 'id' => 'recent_updated', 'name' => '最近更新的', 'query' => [ 'updated_at' => '2w' ] ],
            [ 'id' => 'recent_resolved', 'name' => '最近解决的', 'query' => [ 'resolved_at' => '2w' ] ],
            [ 'id' => 'recent_closed', 'name' => '最近关闭的', 'query' => [ 'closed_at' => '2w' ] ],
        ];
    }

    /**
     * get the project filters.
     *
     * @param  string $project_key
     * @param  string $user_id
     * @return array
     */
    public static function getIssueFilters($project_key, $user_id)
    {
        // default issue filters
        $filters = self::getDefaultIssueFilters();

        $res = UserIssueFilters::where('project_key', $project_key)
            ->where('user', $user_id)
            ->first(); 
        if ($res)
        {
            $filters = isset($res->filters) ? $res->filters : [];
        }
        return $filters;
    }

    /**
     * get the issue list default columns.
     *
     * @return array
     */
    public static function getDefaultDisplayColumns($project_key)
    {
        $res = ProjectIssueListColumns::where('project_key', $project_key)->first(); 
        if ($res)
        {
            $columns = isset($res->columns) ? $res->columns : [];
            return $columns;
        }

        return [
            [ 'key' => 'assignee', 'width' => '100' ],
            [ 'key' => 'priority', 'width' => '70' ],
            [ 'key' => 'state', 'width' => '100' ],
            [ 'key' => 'resolution', 'width' => '100' ],
        ];
    }

    /**
     * get the issue list columns.
     *
     * @param  string $project_key
     * @param  string $user_id
     * @return array
     */
    public static function getIssueDisplayColumns($project_key, $user_id)
    {
        // default issue filters
        $columns = self::getDefaultDisplayColumns($project_key);
        $res = UserIssueListColumns::where('project_key', $project_key)
            ->where('user', $user_id)
            ->first(); 
        if ($res)
        {
            $columns = isset($res->columns) ? $res->columns : [];
        }
        return $columns;
    }

    /**
     * get state list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection 
     */
    public static function getStateList($project_key, $fields=[])
    {
        $states = State::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('sn', 'asc')
            ->get($fields)
            ->toArray();

        $stateProperty = StateProperty::Where('project_key', $project_key)->first();
        if ($stateProperty)
        {
            if ($sequence = $stateProperty->sequence)
            {
                $func = function($v1, $v2) use ($sequence) {
                    $i1 = array_search($v1['_id'], $sequence);
                    $i1 = $i1 !== false ? $i1 : 998;
                    $i2 = array_search($v2['_id'], $sequence);
                    $i2 = $i2 !== false ? $i2 : 999;
                    return $i1 >= $i2 ? 1 : -1;
                };
                usort($states, $func);
            }
        }

        return $states;
    }

    /**
     * get state options.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getStateOptions($project_key)
    {
        $states = self::getStateList($project_key);

        $options = [];
        foreach ($states as $state)
        {
            $tmp = [];
            $tmp['_id'] = isset($state['key']) && $state['key'] ? $state['key'] : $state['_id'];
            $tmp['name'] = isset($state['name']) ? trim($state['name']) : '';
            $tmp['category'] = isset($state['category']) ? $state['category'] : '';
            $options[] = $tmp;
        }
        return $options;
    }

    /**
     * get state options.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getLabelOptions($project_key)
    {
        $options = [];

        $labels = Labels::Where('project_key', $project_key)
            ->orderBy('_id', 'desc')
            ->get();
        foreach ($labels as $label)
        {
            $options[] = $label->name;
        }

        return $options;
    }

    /**
     * get event list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection 
     */
    public static function getEventList($project_key, $fields=[])
    {
        $events = Events::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('_id', 'asc')
            ->get($fields);

        return $events;
    }

    /**
     * get event options.
     *
     * @param string $project_key
     * @return collection
     */
    public static function getEventOptions($project_key)
    {
        $events = self::getEventList($project_key); 

        $options = [];
        foreach ($events as $event)
        {
            if (!isset($event->apply) || $event->apply !== 'workflow')
            {
                continue;
            }

            $tmp = [];
            $tmp['_id'] = isset($event['key']) ? $event['key'] : $event['_id'];
            $tmp['name'] = isset($event['name']) ? trim($event['name']) : '';
            $options[] = $tmp;
        }

        return $options;
    }

    /**
     * get default priority.
     *
     * @param string $project_key
     * @return string 
     */
    public static function getDefaultPriority($project_key)
    {
        $priorityProperty = PriorityProperty::Where('project_key', $project_key)->first();
        if ($priorityProperty)
        {
            if ($defaultValue = $priorityProperty->defaultValue)
            {
                return $defaultValue;
            }
        }

        $priority = Priority::whereRaw([ 'project_key' => [ '$in' =>  [ '$_sys_$', $project_key ] ] ])
            ->Where('default', true)
            ->first();

        $default = $priority && isset($priority->key) && $priority->key ? $priority->key : $priority->id;
        return $default; 
    }

    /**
     * get priority list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getPriorityList($project_key, $fields=[])
    {
        $priorities = Priority::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('sn', 'asc')
            ->get($fields)
            ->toArray();

        $priorityProperty = PriorityProperty::Where('project_key', $project_key)->first();
        if ($priorityProperty)
        {
            if ($sequence = $priorityProperty->sequence)
            {
                $func = function($v1, $v2) use ($sequence) {
                    $i1 = array_search($v1['_id'], $sequence);
                    $i1 = $i1 !== false ? $i1 : 998;
                    $i2 = array_search($v2['_id'], $sequence);
                    $i2 = $i2 !== false ? $i2 : 999;
                    return $i1 >= $i2 ? 1 : -1;
                };
                usort($priorities, $func);
            }

            if ($defaultValue = $priorityProperty->defaultValue)
            {
                foreach($priorities as $key => $val)
                {
                    if ($val['_id'] == $defaultValue)
                    {
                        $priorities[$key]['default'] = true;
                    }
                    else if (isset($val['default']))
                    {
                        unset($priorities[$key]['default']);
                    }
                }
            }
        }

        return $priorities;
    }

    /**
     * get priority options.
     *
     * @param string $project_key
     * @return array
     */
    public static function getPriorityOptions($project_key)
    {
        $priorities = self::getPriorityList($project_key);

        $options = [];
        foreach ($priorities as $priority)
        {
            $tmp = [];
            $tmp['_id'] = isset($priority['key']) && $priority['key'] ? $priority['key'] : $priority['_id'];
            $tmp['name'] = isset($priority['name']) ? trim($priority['name']) : '';
            if (isset($priority['default']))
            {
                $tmp['default'] = $priority['default'];
            }
            if (isset($priority['color']))
            {
                $tmp['color'] = $priority['color'];
            }
            $options[] = $tmp;
        }
        return $options;
    }

    /**
     * get default resolution.
     *
     * @param string $project_key
     * @return string 
     */
    public static function getDefaultResolution($project_key)
    {
        $resolutionProperty = ResolutionProperty::Where('project_key', $project_key)->first();
        if ($resolutionProperty)
        {
            if ($defaultValue = $resolutionProperty->defaultValue)
            {
                return $defaultValue;
            }
        }

        $resolution = Resolution::whereRaw([ 'project_key' => [ '$in' =>  [ '$_sys_$', $project_key ] ] ])
            ->Where('default', true)
            ->first();

        $default = $resolution && isset($resolution->key) && $resolution->key ? $resolution->key : $resolution->id;
        return $default;
    }

    /**
     * get resolution list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getResolutionList($project_key, $fields=[])
    {
        $resolutions = Resolution::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('sn', 'asc')
            ->get($fields)
            ->toArray();

        $resolutionProperty = ResolutionProperty::Where('project_key', $project_key)->first();
        if ($resolutionProperty)
        {
            if ($sequence = $resolutionProperty->sequence)
            {
                $func = function($v1, $v2) use ($sequence) {
                    $i1 = array_search($v1['_id'], $sequence);
                    $i1 = $i1 !== false ? $i1 : 998;
                    $i2 = array_search($v2['_id'], $sequence);
                    $i2 = $i2 !== false ? $i2 : 999;
                    return $i1 >= $i2 ? 1 : -1;
                };
                usort($resolutions, $func);
            }

            if ($defaultValue = $resolutionProperty->defaultValue)
            {
                foreach($resolutions as $key => $val)
                {
                    if ($val['_id'] == $defaultValue)
                    {
                        $resolutions[$key]['default'] = true;
                    }
                    else if (isset($val['default']))
                    {
                        unset($resolutions[$key]['default']);
                    }
                }
            }
        }

        return $resolutions;
    }

    /**
     * get resolution options.
     *
     * @param string $project_key
     * @return array 
     */
    public static function getResolutionOptions($project_key)
    {
        $resolutions = self::getResolutionList($project_key);

        $options = [];
        foreach ($resolutions as $resolution)
        {
            $tmp = [];
            $tmp['_id'] = isset($resolution['key']) && $resolution['key'] ? $resolution['key'] : $resolution['_id'];
            $tmp['name'] = isset($resolution['name']) ? trim($resolution['name']) : '';
            if (isset($resolution['default']))
            {
                $tmp['default'] = $resolution['default'];
            }
            $options[] = $tmp;
        }
        return $options;
    }

    /**
     * get screen list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getScreenList($project_key, $fields=[])
    {
        $screens = Screen::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('_id', 'asc')
            ->get($fields);

        return $screens;
    }

    /**
     * get field list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getFieldList($project_key, $fields=[])
    {
        $fields = Field::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('_id', 'asc')
            ->get($fields);

        return $fields;
    }

    /**
     * get workflow list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getWorkflowList($project_key, $fields=[])
    {
        $workflows = Definition::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('_id', 'asc')
            ->get($fields);

        return $workflows;
    }


    /**
     * get type list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getTypeList($project_key, $fields=[])
    {
        $types = Type::Where('project_key', $project_key)
            ->orderBy('sn', 'asc')
            ->get($fields);

        return $types;
    }

    /**
     * get role list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getRoleList($project_key, $fields=[])
    {
        $roles = Role::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->orderBy('project_key', 'asc')
            ->orderBy('_id', 'asc')
            ->get($fields);

        return $roles;
    }

    /**
     * get user list.
     *
     * @param string $project_key
     * @return array
     */
    public static function getUserList($project_key)
    {
        $user_group_ids = UserGroupProject::Where('project_key', $project_key)
            ->Where('link_count', '>', 0)
            ->get(['ug_id', 'type']);

        $user_ids = [];
        $group_ids = [];
        foreach ($user_group_ids as $value)
        {
            if (isset($value->type) && $value->type === 'group')
            {
                $group_ids[] = $value->ug_id;
            }
            else
            {
                $user_ids[] = $value->ug_id;
            }
        }

        if ($group_ids)
        {
            $groups = Group::find($group_ids);
            foreach($groups as $group)
            {
                $user_ids = array_merge($user_ids, isset($group->users) && $group->users ? $group->users : []);
            }
        }
        $user_ids = array_unique($user_ids);

        $user_list = [];
        $users = EloquentUser::find($user_ids);
        foreach ($users as $user)
        {
            if (isset($user->invalid_flag) && $user->invalid_flag === 1)
            {
                continue;
            }
            $user_list[] = ['id' => $user->id, 'name' => $user->first_name, 'email' => $user->email ];
        }

        return $user_list;
    }

    /**
     * get assigned user list.
     *
     * @param string $project_key
     * @return array
     */
    public static function getAssignedUsers($project_key)
    {
        $user_ids = Acl::getUserIdsByPermission('assigned_issue', $project_key);

        $user_list = [];
        $users = EloquentUser::find($user_ids); 
        foreach ($users as $user)
        {
            if (isset($user->invalid_flag) && $user->invalid_flag === 1)
            {
                continue;
            }
            $user_list[] = [ 'id' => $user->id, 'name' => $user->first_name, 'email' => $user->email ];
        }
        return $user_list;
    }

    /**
     * get version list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getVersionList($project_key, $fields=[])
    {
        $versions = Version::where([ 'project_key' => $project_key ])
            ->orderBy('status', 'desc')
            ->orderBy('released_time', 'desc')
            ->orderBy('end_time', 'desc')
            ->orderBy('created_at', 'desc')
            ->get($fields);

        return $versions;
    }

    /**
     * get module list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getModuleList($project_key, $fields=[])
    {
        $modules = Module::where([ 'project_key' => $project_key ])
            ->orderBy('sn', 'asc')
            ->get($fields);

        return $modules;
    }

    /**
     * check if type has existed.
     *
     * @param string $project_key
     * @param string $name 
     * @return bool
     */
    public static function isTypeExisted($project_key, $name)
    {
        $isExisted = Type::Where('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * check if type abb has existed.
     *
     * @param string $project_key
     * @param string $abb 
     * @return bool
     */
    public static function isTypeAbbExisted($project_key, $abb)
    {
        $isExisted = Type::Where('project_key', $project_key)
            ->Where('abb', $abb)
            ->exists();

        return $isExisted;
    }

    /**
     * check if state has existed.
     *
     * @param string $project_key
     * @param string $name 
     * @return bool
     */
    public static function isStateExisted($project_key, $name)
    {
        $isExisted = State::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * check if priority has existed.
     *
     * @param string $project_key
     * @param string $name
     * @return bool
     */
    public static function isPriorityExisted($project_key, $name)
    {
        $isExisted = Priority::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * check if resolution has existed.
     *
     * @param string $project_key
     * @param string $name
     * @return bool
     */
    public static function isResolutionExisted($project_key, $name)
    {
        $isExisted = Resolution::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * check if field has existed.
     *
     * @param string $project_key
     * @param string $key
     * @return bool
     */
    public static function isFieldKeyExisted($project_key, $key)
    {
        $fields = Field::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->get();
        foreach ($fields as $field)
        {
            if ($field->key === $key || ($field->type === 'MutiUser' && $field->key . '_ids' === $key) || ($field->type === 'TimeTracking' && $field->key . '_m' === $key))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * check if Event has existed.
     *
     * @param string $project_key
     * @param string $name
     * @return bool
     */
    public static function isEventExisted($project_key, $name)
    {
        $isExisted = Events::Where('project_key', '$_sys_$')
            ->orWhere('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * get issue type schema 
     *
     * @param string $project_key
     * @return array 
     */
    public static function getTypeListExt($project_key, $options)
    {
        $typeOptions = [];
        $types = self::getTypeList($project_key);
        foreach ($types as $key => $type)
        {
            $schema = self::_repairSchema($project_key, $type->id, $type->screen && $type->screen->schema ? $type->screen->schema : [] , $options);

            $tmp = [ 'id' => $type->id, 'name' => $type->name, 'abb' => $type->abb, 'disabled' => $type->disabled && true, 'type' => $type->type == 'subtask' ? 'subtask' : 'standard', 'schema' => $schema ];
            if ($type->default) 
            {
                $tmp['default'] = true;
            }
            $typeOptions[] = $tmp;
        }
        return $typeOptions;
    }

    /**
     * get issue type schema
     *
     * @param string $project_key
     * @return array
     */
    private static function _repairSchema($project_key, $issue_type, $schema, $options)
    {
        $new_schema = [];
        foreach ($schema as $key => $val)
        {
            if (isset($val['applyToTypes']))
            {
                if ($val['applyToTypes'] && !in_array($issue_type, explode(',', $val['applyToTypes'] ?: '')))
                {
                    continue;
                }
                unset($val['applyToTypes']);
            }

            if ($val['type'] == 'SingleVersion' || $val['type'] == 'MultiVersion')
            {
                if (!isset($options['version']))
                {
                    $options['version'] = self::getVersionList($project_key);
                }
                $val['optionValues'] = self::pluckFields($options['version'], ['_id', 'name']);
            }
            else if ($val['type'] == 'SingleUser' || $val['type'] == 'MultiUser')
            {
                $val['optionValues'] = self::pluckFields($options['user'], ['id', 'name', 'email']);
                foreach ($val['optionValues'] as $k => $v)
                {
                    $val['optionValues'][$k]['name'] = $v['name'] . '(' . $v['email'] . ')';
                    unset($val['optionValues'][$k]['email']);
                }
            }
            else if ($val['key'] == 'assignee')
            {
                $val['optionValues'] = self::pluckFields($options['assignee'], ['id', 'name', 'email']);
                foreach ($val['optionValues'] as $k => $v)
                {
                    $val['optionValues'][$k]['name'] = $v['name'] . '(' . $v['email'] . ')';
                    unset($val['optionValues'][$k]['email']);
                }
            }
            else if ($val['key'] == 'labels')
            {
                $couple_labels = [];
                foreach ($options['labels'] as $label)
                {
                    $couple_labels[] = [ 'id' => $label, 'name' => $label ];
                }
                $val['optionValues'] = $couple_labels;
            }
            else if (array_key_exists($val['key'], $options))
            {
                $val['optionValues'] = self::pluckFields($options[$val['key']], ['_id', 'name']);
                foreach ($options[$val['key']] as $key2 => $val2) 
                {
                    if (isset($val2['default']) && $val2['default'])
                    {
                        $val['defaultValue'] = $val2['_id'];
                        break;
                    }
                }
            }
            if (isset($val['_id']))
            {
                unset($val['_id']);
            }
            $new_schema[] = $val;
        }
        return $new_schema;
    }

    /**
     * filter the fields.
     *
     * @param array $srcData
     * @param array $fields
     * @return array 
     */
    public static function pluckFields($srcData, $fields)
    {
        $destData = [];
        foreach ($srcData as $val)
        {
            $tmp = [];
            foreach ($fields as $field)
            {
                if ($field === '_id') {
                    if (isset($val[$field]) && $val[$field] instanceof ObjectID) {
                        $tmp['id'] = $val[$field]->__toString();
                    } else {
                        $tmp['id'] = isset($val[$field]) ? $val[$field] : '';
                    }
                } else {
                    $tmp[$field] = isset($val[$field]) ? $val[$field] : '';
                }
            }
            $destData[] = $tmp;
        } 
        return $destData;
    }

    /**
     * get module principal.
     *
     * @param string $project_key
     * @param string $mid
     * @return array 
     */
    public static function getModuleById($mid)
    {
        $module = Module::find($mid)->toArray();
        return $module ?: [];
    }

    /**
     * get workflow by type_id
     *
     * @param string $type_id
     * @return array
     */
    public static function getWorkflowByType($type_id)
    {
        $type = Type::find($type_id);
        return $type->workflow;
    }

    /**
     * get schema by type_id
     *
     * @param string $type_id
     * @return array
     */
    public static function getSchemaByType($type_id)
    {
        $type = Type::find($type_id);
        if (!$type)
        {
            return [];
        }

        $screen = $type->screen;
        $project_key = $type->project_key;

        return self::getScreenSchema($project_key, $type_id, $screen);
    }

    /**
     * get schema by screen_id
     *
     * @param string $project_key
     * @param string $type
     * @param string $screen_id
     * @return array
     */
    public static function getSchemaByScreenId($project_key, $type, $screen_id)
    {
        $screen = Screen::find($screen_id);
        if (!$screen)
        {
            return [];
        }
        return self::getScreenSchema($project_key, $type, $screen);
    }

    /**
     * get screen schema
     *
     * @param string $type_id
     * @return array
     */
    public static function getScreenSchema($project_key, $type_id, $screen)
    {
        $new_schema = [];
        $versions = null;
        $users = null;
        foreach ($screen->schema ?: [] as $key => $val)
        {
            if (isset($val['applyToTypes']))
            {
                if ($val['applyToTypes'] && !in_array($type_id, explode(',', $val['applyToTypes'] ?: '')))
                {
                    continue;
                }
                unset($val['applyToTypes']);
            }

            if ($val['key'] == 'assignee')
            {
                $users = self::getAssignedUsers($project_key);
                foreach ($users as $key => $user)
                {
                    $users[$key]['name'] = $user['name'] . '(' . $user['email'] . ')';
                } 
                $val['optionValues'] = self::pluckFields($users, ['id', 'name']);
            }
            else if ($val['key'] == 'resolution')
            {
                $resolutions = self::getResolutionOptions($project_key);
                $val['optionValues'] = self::pluckFields($resolutions, ['_id', 'name']);
                foreach ($resolutions as $key2 => $val2)
                {
                    if (isset($val2['default']) && $val2['default'])
                    {
                        $val['defaultValue'] = $val2['_id'];
                        break;
                    }
                }
            }
            else if ($val['key'] == 'priority')
            {
                $priorities = self::getPriorityOptions($project_key);
                $val['optionValues'] = self::pluckFields($priorities, ['_id', 'name']);
                foreach ($priorities as $key2 => $val2)
                {
                    if (isset($val2['default']) && $val2['default'])
                    {
                        $val['defaultValue'] = $val2['_id'];
                        break;
                    }
                }
            }
            else if ($val['key'] == 'module')
            {
                $modules = self::getModuleList($project_key);
                $val['optionValues'] = self::pluckFields($modules, ['_id', 'name']);
            }
            else if ($val['key'] == 'epic')
            {
                $epics = self::getEpicList($project_key);
                $val['optionValues'] = self::pluckFields($epics, ['_id', 'name', 'bgColor']);
            }
            else if ($val['key'] == 'labels')
            {
                $labels = self::getLabelOptions($project_key);
                $couple_labels = [];
                foreach ($labels as $label)
                {
                    $couple_labels[] = [ 'id' => $label, 'name' => $label ];
                }
                $val['optionValues'] = $couple_labels;
            }
            else if ($val['type'] == 'SingleVersion' || $val['type'] == 'MultiVersion')
            {
                $versions === null && $versions = self::getVersionList($project_key);
                $val['optionValues'] = self::pluckFields($versions, ['_id', 'name']);
            }
            else if ($val['type'] == 'SingleUser' || $val['type'] == 'MultiUser')
            {
                $users === null && $users = self::getUserList($project_key);
                foreach ($users as $key => $user)
                {
                    $users[$key]['name'] = $user['name'] . '(' . $user['email'] . ')';
                }
                $val['optionValues'] = self::pluckFields($users, ['id', 'name']);
            }

            if (isset($val['_id']))
            {
                unset($val['_id']);
            }

            $new_schema[] = $val;
        }

        return $new_schema;
    }

    /**
     * snap issue data to history
     *
     * @param  string  $project_key
     * @param  string  $issue_id
     * @param  array  $schema
     * @param  array $change_fields
     * @return \Illuminate\Http\Response
     */
    public static function snap2His($project_key, $issue_id, $schema = [], $change_fields=[])
    {
        //获取问题数据
        $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();

        $latest_ver_issue = DB::collection('issue_his_' . $project_key)->where('issue_id', $issue_id)->orderBy('_id', 'desc')->first();
        if ($latest_ver_issue)
        {
            $snap_data = $latest_ver_issue['data'];
        }
        else
        {
            $snap_data = [];
        }

        // fetch the schema data
        if (!$schema)
        {
            $schema = [];
            if ($change_fields)
            {
                $out_schema_fields = [ 'type', 'state', 'resolution', 'priority', 'assignee', 'labels', 'parent_id', 'progress', 'expect_start_time', 'expect_complete_time' ];
                if (array_diff($change_fields, $out_schema_fields))
                {
                    $schema = self::getSchemaByType($issue['type']);
                }
            }
            else
            {
                $schema = self::getSchemaByType($issue['type']);
            }
        }

        foreach ($schema as $field)
        {
            if (in_array($field['key'], [ 'assignee', 'progress' ]) || ($change_fields && !in_array($field['key'], $change_fields)))
            {
                continue;
            }

            if (isset($issue[$field['key']]))
            {
                $val = [];
                $val['name'] = $field['name'];
                
                if ($field['type'] === 'SingleUser' || $field['type'] === 'MultiUser')
                {
                    if ($field['type'] === 'SingleUser')
                    {
                        $val['value'] = $issue[$field['key']] ? $issue[$field['key']]['name'] : $issue[$field['key']];
                    }
                    else
                    {
                        $tmp_users = [];
                        if ($issue[$field['key']])
                        {
                            foreach ($issue[$field['key']] as $tmp_user)
                            {
                                $tmp_users[] = $tmp_user['name'];
                            }
                        }
                        $val['value'] = implode(',', $tmp_users);
                    }
                }
                else if (isset($field['optionValues']) && $field['optionValues'])
                {
                    $opv = [];
                    
                    if (!is_array($issue[$field['key']]))
                    {
                        $fieldValues = explode(',', $issue[$field['key']]);
                    }
                    else
                    {
                        $fieldValues = $issue[$field['key']];
                    }
                    foreach ($field['optionValues'] as $ov)
                    {
                        if (in_array($ov['id'], $fieldValues))
                        {
                            $opv[] = $ov['name'];
                        }
                    }
                    $val['value'] = implode(',', $opv);
                }
                else if ($field['type'] == 'File')
                {
                    $val['value'] = [];
                    foreach ($issue[$field['key']] as $fid)
                    {
                        $file = File::find($fid);
                        array_push($val['value'], $file->name);
                    }
                }
                else if ($field['type'] == 'DatePicker' || $field['type'] == 'DateTimePicker')
                {
                    $val['value'] = $issue[$field['key']] ? date($field['type'] == 'DatePicker' ? 'Y/m/d' : 'Y/m/d H:i:s', $issue[$field['key']]) : $issue[$field['key']];
                }
                else
                {
                    $val['value'] = $issue[$field['key']];
                }
                //$val['type'] = $field['type']; 

                $snap_data[$field['key']] = $val;
            }
        }

        // special fields handle
        if (in_array('type', $change_fields) || !isset($snap_data['type']))
        {
            $type = Type::find($issue['type']);
            $snap_data['type'] = [ 'value' => isset($type->name) ? $type->name : '', 'name' => '类型' ];
        }

        if (isset($issue['priority']))
        {
            if ($issue['priority'])
            { 
                if (in_array('priority', $change_fields) || !isset($snap_data['priority']))
                {
                    $priority = Priority::Where('key', $issue['priority'])->orWhere('_id', $issue['priority'])->first();
                    $snap_data['priority'] = [ 'value' => isset($priority->name) ? $priority->name : '', 'name' => '优先级' ];
                }
            }
            else
            {
                $snap_data['priority'] = [ 'value' => '', 'name' => '优先级' ];
            }
        }

        if (isset($issue['state']))
        {
            if ($issue['state'])
            {
                if (in_array('state', $change_fields) || !isset($snap_data['state']))
                {
                    $state = State::Where('key', $issue['state'])->orWhere('_id', $issue['state'])->first();
                    $snap_data['state'] = [ 'value' => isset($state->name) ? $state->name : '', 'name' => '状态' ];
                }
            }
            else
            {
                $snap_data['state'] = [ 'value' => '', 'name' => '状态' ];
            }
        }

        if (isset($issue['resolution']))
        {
            if ($issue['resolution'])
            {
                if (in_array('resolution', $change_fields) || !isset($snap_data['resolution']))
                {
                    $resolution = Resolution::Where('key', $issue['resolution'])->orWhere('_id', $issue['resolution'])->first();
                    $snap_data['resolution'] = [ 'value' => isset($resolution->name) ? $resolution->name : '', 'name' => '解决结果' ];
                }
            }
            else
            {
                $snap_data['resolution'] = [ 'value' => '', 'name' => '解决结果' ];
            }
        }

        if (isset($issue['assignee']))
        {
            if ($issue['assignee'])
            {
                if (in_array('assignee', $change_fields) || !isset($snap_data['assignee']))
                {
                    $snap_data['assignee'] = [ 'value' => $issue['assignee']['name'], 'name' => '经办人' ];
                }
            }
            else
            {
                $snap_data['assignee'] = [ 'value' => '', 'name' => '经办人' ];
            }
        }
        // labels
        if (isset($issue['labels']))
        {
            if ($issue['labels'])
            {
                if (in_array('labels', $change_fields) || !isset($snap_data['labels']))
                {
                    $snap_data['labels'] = [ 'value' => implode(',', $issue['labels']), 'name' => '标签' ];
                }
            }
            else
            {
                $snap_data['labels'] = [ 'value' => '', 'name' => '标签' ];
            }
        }

        // special fields handle
        if (isset($issue['parent_id']))
        {
            if ($issue['parent_id'])
            {
                if (in_array('parent_id', $change_fields) || !isset($snap_data['parent_id']))
                {
                    $parent = DB::collection('issue_' . $project_key)->where('_id', $issue['parent_id'])->first(['no', 'title']);
                    $snap_data['parent'] = [ 'value' => $parent['no'] . ' - ' . $parent['title'], 'name' => '父任务' ];
                }
            }
            else
            {
                $snap_data['parent'] = [ 'value' => '', 'name' => '父任务' ];
            }
        }

        if (isset($issue['progress']))
        {
            if ($issue['progress'] || $issue['progress'] === 0)
            {
                if (in_array('progress', $change_fields) || !isset($snap_data['progress']))
                {
                    $snap_data['progress'] = [ 'value' => $issue['progress'] . '%', 'name' => '进度' ];
                }
            }
            else
            {
                $snap_data['progress'] = [ 'value' => '', 'name' => '进度' ];
            }
        }

        if (isset($issue['expect_start_time']))
        {
            if ($issue['expect_start_time'])
            {
                if (in_array('expect_start_time', $change_fields) || !isset($snap_data['expect_start_time']))
                {
                    $snap_data['expect_start_time'] = [ 'value' => date('Y/m/d', $issue['expect_start_time']), 'name' => '期望开始时间' ];
                }
            }
            else
            {
                $snap_data['expect_start_time'] = [ 'value' => date('Y/m/d', $issue['expect_start_time']), 'name' => '期望开始时间' ];
            }
        }

        if (isset($issue['expect_complete_time']))
        {
            if ($issue['expect_complete_time'])
            {
                if (in_array('expect_complete_time', $change_fields) || !isset($snap_data['expect_complete_time']))
                {
                    $snap_data['expect_complete_time'] = [ 'value' => date('Y/m/d', $issue['expect_complete_time']), 'name' => '期望完成时间' ];
                }
            }
            else
            {
                $snap_data['expect_complete_time'] = [ 'value' => '', 'name' => '期望完成时间' ];
            }
        }

        if (!isset($snap_data['created_at']))
        {
            $snap_data['created_at'] = $issue['created_at'];
        }

        if (!isset($snap_data['reporter']))
        {
            $snap_data['reporter'] = $issue['reporter'];
        }

        $operated_at = isset($issue['updated_at']) ? $issue['updated_at'] : $issue['created_at'];
        $operator = isset($issue['modifier']) ? $issue['modifier'] : $issue['reporter'];

        $snap_id = DB::collection('issue_his_' . $project_key)->insertGetId([ 'issue_id' => $issue['_id']->__toString(), 'operated_at' => $operated_at, 'operator' => $operator, 'data' => $snap_data ]);

        return $snap_id->__toString();
    }

    /**
     * check if issue exist.
     *
     * @param string $project_key
     * @param string $issue_id
     * @return bool
     */
    public static function isIssueExisted($project_key, $issue_id)
    {
        $isExisted = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->exists();
        return $isExisted;
    }

    /**
     * get all subtasks of the parent  
     *
     * @param string $project_key
     * @param string $parent_no
     * @return bool
     */
    public static function getChildrenByParentNo($project_key, $parent_no)
    {
        $parent = DB::collection('issue_' . $project_key)->where('no', $parent_no)->first();
        if (!$parent) { return []; }

        $children = [];
        $subtasks = DB::collection('issue_' . $project_key)->where('parent_id', $parent['_id']->__toString())->get(['no']);
        foreach ($subtasks as $subtask)
        {
            $children[] = $subtask['no'];
        }
        return $children;
    }

    /**
     * get epic list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getEpicList($project_key, $fields=[])
    {
        $epics = Epic::Where('project_key', $project_key)
            ->orderBy('sn', 'asc')
            ->get($fields)
            ->toArray();

        return $epics;
    }

    /**
     * check if Epic has existed.
     *
     * @param string $project_key
     * @param string $name
     * @return bool
     */
    public static function isEpicExisted($project_key, $name)
    {
        $isExisted = Epic::Where('project_key', $project_key)
            ->Where('name', $name)
            ->exists();

        return $isExisted;
    }

    /**
     * get sprint list.
     *
     * @param string $project_key
     * @return collection
     */
    public static function getSprintList($project_key, $fields=[])
    {
        $epics = Sprint::Where('project_key', $project_key)
            ->WhereIn('status', [ 'active', 'completed' ])
            ->orderBy('no', 'desc')
            ->get($fields)
            ->toArray();

        return $epics;
    }
}

