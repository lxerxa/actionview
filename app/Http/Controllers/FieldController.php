<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\FieldChangeEvent;
use App\Events\FieldDeleteEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use App\Project\Eloquent\Project;
use App\Project\Provider;

use DB;

class FieldController extends Controller
{
    private $special_fields = [
        'id', 
        'type', 
        'state', 
        'reporter', 
        'modifier', 
        'created_at', 
        'updated_at', 
        'resolved_at', 
        'closed_at', 
        'regression_times', 
        'his_resolvers',
        'resolved_logs',
        'no', 
        'schema', 
        'parent_id', 
        'parent', 
        'links', 
        'subtasks', 
        'entry_id', 
        'definition_id', 
        'comments_num', 
        'worklogs_num', 
        'gitcommits_num', 
        'sprint', 
        'sprints', 
        'filter', 
        'from',
        'from_kanban_id',
        'limit',
        'page',
        'orderBy',
        'stat_x',
        'stat_y',
    ];

    private $sys_fields = [
        'title',
        'priority',
        'resolution',
        'assignee',
        'module',
        'comments',
        'resolve_version',
        'effect_versions',
        'expect_complete_time',
        'expect_start_time',
        'progress',
        'related_users',
        'descriptions',
        'epic',
        'labels',
        'original_estimate',
        'story_points',
        'attachments'
    ];

    private $all_types = [
        'Tags', 
        'Number', 
        'Text', 
        'TextArea', 
        'Select', 
        'MultiSelect', 
        'RadioGroup', 
        'CheckboxGroup', 
        'DatePicker', 
        'DateTimePicker', 
        'TimeTracking', 
        'File', 
        'SingleVersion', 
        'MultiVersion', 
        'SingleUser', 
        'MultiUser', 
        'Url'
    ];
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $fields = Provider::getFieldList($project_key);
        foreach ($fields as $field)
        {
            $field->screens = Screen::whereRaw([ 'field_ids' => $field->id ])
                ->orderBy('project_key', 'asc')
                ->get(['project_key', 'name'])
                ->toArray();

            $field->is_used = !!($field->screens);

            $field->screens = array_filter($field->screens, function($item) use($project_key) { 
                return $item['project_key'] === $project_key || $item['project_key'] === '$_sys_$';
            });
        }
        $types = Provider::getTypeList($project_key, ['name']);
        return Response()->json(['ecode' => 0, 'data' => $fields, 'options' => [ 'types' => $types ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name cannot be empty.', -12200);
        }

        $key = $request->input('key');
        if (!$key)
        {
            throw new \InvalidArgumentException('field key cannot be empty.', -12201);
        }

        if (in_array($key, $this->special_fields))
        {
            throw new \InvalidArgumentException('field key has been used by system.', -12202);
        }

        if (Provider::isFieldKeyExisted($project_key, $key))
        {
            throw new \InvalidArgumentException('field key cannot be repeated.', -12203);
        }

        $type = $request->input('type');
        if (!$type)
        {
            throw new \UnexpectedValueException('the type cannot be empty.', -12204);
        }

        if ($type === 'TimeTracking' && Provider::isFieldKeyExisted($project_key, $key . '_m'))
        {
            throw new \InvalidArgumentException('field key cannot be repeated.', -12203);
        }

        if ($type === 'MultiUser' && Provider::isFieldKeyExisted($project_key, $key . '_ids'))
        {
            throw new \UnexpectedValueException('the type cannot be empty.', -12204);
        }

        if (!in_array($type, $this->all_types))
        {
            throw new \UnexpectedValueException('the type is incorrect type.', -12205);
        }

        $optionTypes = [ 'Select', 'MultiSelect', 'RadioGroup', 'CheckboxGroup' ];
        if (in_array($type, $optionTypes))
        {
            $optionValues = $request->input('optionValues') ?: [];
            foreach ($optionValues as $key => $val)
            {
                if (!isset($val['name']) || !$val['name'])
                {
                    continue;
                }
                $optionValues[$key]['id'] = md5(microtime() . $val['name']);
            }

            $defaultValue = $request->input('defaultValue') ?: '';
            if ($defaultValue)
            {
                $defaults = is_array($defaultValue) ? $defaultValue : explode(',', $defaultValue);
                $options = array_column($optionValues, 'id');
                if ('MultiSelect' === $type || 'CheckboxGroup' === $type)
                {
                    $defaultValue = array_values(array_intersect($defaults, $options));
                }
                else
                {
                    $defaultValue = implode(',', array_intersect($defaults, $options));
                }
            }
            $field = Field::create([ 'project_key' => $project_key, 'optionValues' => $optionValues, 'defaultValue' => $defaultValue ] + $request->all());
        }
        else
        {
            $field = Field::create([ 'project_key' => $project_key ] + $request->all());
        }
        return Response()->json(['ecode' => 0, 'data' => $field]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $field = Field::find($id);
        //if (!$field || $project_key != $field->project_key)
        //{
        //    throw new \UnexpectedValueException('the field does not exist or is not in the project.', -10002);
        //}
        // get related screen
        $field->screens = Screen::whereRaw([ 'field_ids' => $id ])->get(['name']);

        return Response()->json(['ecode' => 0, 'data' => $field]);
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
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12200);
            }
        }

        $field = Field::find($id);
        if (!$field || $project_key != $field->project_key)
        {
            throw new \UnexpectedValueException('the field does not exist or is not in the project.', -12206);
        }

        $updValues = [];

        $optionTypes = [ 'Select', 'MultiSelect', 'RadioGroup', 'CheckboxGroup' ];
        if (in_array($field->type, $optionTypes))
        {
            $optionValues = $request->input('optionValues');
            $defaultValue = $request->input('defaultValue');
            if (isset($optionValues) || isset($defaultValue))
            {
                if (isset($optionValues))
                {
                    if (isset($field->optionValues) && $field->optionValues)
                    {
                        $old_option_ids = array_column($field->optionValues, 'id');
                    }
                    else
                    {
                        $old_option_ids = [];
                    }

                    foreach ($optionValues as $key => $val)
                    {
                        if (!isset($val['name']) || !$val['name'])
                        {
                            continue;
                        }

                        if (!isset($val['id']) || !in_array($val['id'], $old_option_ids))
                        {
                            $optionValues[$key]['id'] = md5(microtime() . $val['name']);
                        }
                    }
                }
                else
                {
                    $optionValues = $field->optionValues ?: [];
                }
                $updValues['optionValues'] = $optionValues;

                $options = array_column($optionValues, 'id');
                $defaultValue = isset($defaultValue) ? $defaultValue : ($field->defaultValue ?: '');
                $defaults = is_array($defaultValue) ? $defaultValue : explode(',', $defaultValue);
                if ('MultiSelect' === $field->type || 'CheckboxGroup' === $field->type)
                {
                    $defaultValue = array_values(array_intersect($defaults, $options));
                }
                else
                {
                    $defaultValue = implode(',', array_intersect($defaults, $options));
                }
                $updValues['defaultValue'] = $defaultValue;
            }
        }

        $field->fill($updValues + $request->except(['project_key', 'key', 'type']))->save();

        Event::fire(new FieldChangeEvent($id));

        return Response()->json(['ecode' => 0, 'data' => Field::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $field = Field::find($id);
        if (!$field || $project_key != $field->project_key)
        {
            throw new \UnexpectedValueException('the field does not exist or is not in the project.', -12206);
        }

        if (in_array($field->key, $this->sys_fields))
        {
            throw new \UnexpectedValueException('the field is built in the system.', -12208);
        }

        $isUsed = Screen::whereRaw([ 'field_ids' => $id ])->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the field has been used in screen.', -12207);
        }

        Field::destroy($id);

        Event::fire(new FieldDeleteEvent($project_key, $id, $field->key, $field->type));
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * view the application in the all projects.
     *
     * @return \Illuminate\Http\Response
     */
    public function viewUsedInProject($project_key, $id)
    {
        if ($project_key !== '$_sys_$')
        {
            return Response()->json(['ecode' => 0, 'data' => [] ]);
        }

        $res = [];
        $projects = Project::all();
        foreach($projects as $project)
        {
            $screens = Screen::where('field_ids', $id)
                ->where('project_key', '<>', '$_sys_$')
                ->where('project_key', $project->key)
                ->get([ 'id', 'name' ])
                ->toArray();

            if ($screens)
            {
                $res[] = [ 'key' => $project->key, 'name' => $project->name, 'status' => $project->status, 'screens' => $screens ];
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $res ]);
    }
}
