<?php
namespace App\Project;

use App\Customization\Eloquent\State;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\Type;
use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use App\Acl\Eloquent\Role;
use App\Workflow\Eloquent\Definition;
use App\Project\Eloquent\Project;

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

        $states = State::whereIn('category', [ 'Z', $category ])
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

        $priorities = Priority::whereIn('category', [ 'Z', $category ])
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('sn', 'asc')
            ->get($fields);

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

        $resolutions = Resolution::whereIn('category', [ 'Z', $category ])
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('sn', 'asc')
            ->get($fields);

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

        $screens = Screen::whereIn('category', [ 'Z', $category ])
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

        $fields = Field::whereIn('category', [ 'Z', $category ])
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

        $workflows = Definition::whereIn('category', [ 'Z', $category ])
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

        $roles = Role::whereIn('category', [ 'Z', $category ])
            ->orWhere('project_key', $project_key)
            ->orderBy('category', 'desc')
            ->orderBy('created_at', 'asc')
            ->get($fields);

        return $roles;
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
        
        $isExisted = State::whereIn('category', [ 'Z', $category ])
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

        $isExisted = Priority::whereIn('category', [ 'Z', $category ])
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

        $isExisted = Resolution::whereIn('category', [ 'Z', $category ])
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
    public static function isFieldExisted($project_key, $key)
    {
        $category = self::getProjectCategory($project_key);

        $isExisted = Resolution::whereIn('category', [ 'Z', $category ])
            ->orWhere('project_key', $project_key)
            ->Where('key', $key)
            ->exists();

        return $isExisted;
    }
}
