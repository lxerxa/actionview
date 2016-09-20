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
                   $i1 = $i1 !== false ? $i1 : 999;
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
                   $i1 = $i1 !== false ? $i1 : 999;
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
            $user_info && $user_list[] = ['id' => $user['user_id'], 'name' => $user_info->first_name, 'nameAndEmail' => $user_info->first_name . '(' . $user_info->email . ')' ];
        }

        return $user_list;
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
}
