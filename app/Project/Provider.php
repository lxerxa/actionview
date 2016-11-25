<?php
namespace App\Project;

use App\Customization\Eloquent\State;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\ResolutionProperty;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\PriorityProperty;
use App\Customization\Eloquent\Type;
use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use App\Acl\Eloquent\Role;
use App\Workflow\Eloquent\Definition;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\UserProject;
use Sentinel;
use MongoDB\BSON\ObjectID;
use DB;

class Provider {

    /**
     * get project category.
     *
     * @param string $project_key
     * @return string 
     */
    public static function getProjectCategory($project_key)
    {
        $project = Project::where('key', $project_key)->first();
        return $project && $project->category ? $project->category : '';
    }

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
     * get state list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection 
     */
    public static function getStateList($project_key, $fields=[])
    {
        $category = self::getProjectCategory($project_key);

        $states = State::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
            ->get($fields);

        return $states;
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
        $category = self::getProjectCategory($project_key);

        $priorities = Priority::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
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
     * get resolution list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getResolutionList($project_key, $fields=[])
    {
        $category = self::getProjectCategory($project_key);

        $resolutions = Resolution::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
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
     * get screen list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getScreenList($project_key, $fields=[])
    {
        $category = self::getProjectCategory($project_key);

        $screens = Screen::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
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
        $category = self::getProjectCategory($project_key);

        $fields = Field::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
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
        $category = self::getProjectCategory($project_key);

        $workflows = Definition::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
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
        $category = self::getProjectCategory($project_key);

        $roles = Role::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
            ->get($fields);

        return $roles;
    }

    /**
     * get user list.
     *
     * @param string $project_key
     * @param array $fields
     * @return collection
     */
    public static function getUserList($project_key)
    {
        $users = UserProject::Where('project_key', $project_key)
            ->Where('link_count', '>', 0)
            ->get(['user_id']);

        $user_list = [];
        foreach ($users as $user)
        {
            $user_info = Sentinel::findById($user['user_id']);
            $user_info && $user_list[] = ['id' => $user['user_id'], 'name' => $user_info->first_name . '(' . $user_info->email . ')' ];
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
        $versions = DB::collection('version_' . $project_key)
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
        $versions = DB::collection('module_' . $project_key)
            ->orderBy('created_at', 'asc')
            ->get($fields);

        return $versions;
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
        $category = self::getProjectCategory($project_key);
        
        $isExisted = State::Where('category', $category)
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
        $category = self::getProjectCategory($project_key);

        $isExisted = Priority::Where('category', $category)
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
        $category = self::getProjectCategory($project_key);

        $isExisted = Resolution::Where('category', $category)
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
        $category = self::getProjectCategory($project_key);

        $isExisted = Resolution::Where('category', $category)
            ->orWhere('project_key', $project_key)
            ->Where('key', $key)
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
            $schema = self::_repairSchema($type->id, $type->screen && $type->screen->schema ? $type->screen->schema : [] , $options);

            $tmp = [ 'id' => $type->id, 'name' => $type->name, 'abb' => $type->abb, 'schema' => $schema ];
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
    private static function _repairSchema($issue_type, $schema, $options)
    {
        $new_schema = [];
        foreach ($schema as $key => $val)
        {
            if (isset($val['applyToTypes']))
            {
                if (!in_array($issue_type, explode(',', $val['applyToTypes'] ?: '')))
                {
                    continue;
                }
                unset($val['applyToTypes']);
            }

            if ($val['type'] == 'SingleVersion' || $val['type'] == 'MultiVersion')
            {
                $val['optionValues'] = self::pluckFields($options['version'], ['_id', 'name']);
            }
            else if ($val['key'] == 'assignee')
            {
                $val['optionValues'] = self::pluckFields($options['assignee'], ['id', 'name']);
            }
            else if (array_key_exists($val['key'], $options))
            {
                $val['optionValues'] = self::pluckFields($options[$val['key']], ['_id', 'name']);
                if ($val['key'] == 'module')
                {
                    continue;
                }
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
                $tmp[$field] = isset($val[$field]) ? $val[$field] : '';
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
    public static function getModuleById($project_key, $mid)
    {
        $module = DB::collection('module_' . $project_key)
            ->where('_id', new ObjectId($mid))
            ->first();

        return $module ?: [];
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
        $screen = $type->screen;
        return $screen->schema ?: [];
    }
}
