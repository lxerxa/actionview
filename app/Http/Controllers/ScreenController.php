<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Type;
use App\Customization\Eloquent\Screen;
use App\Customization\Eloquent\Field;
use App\Workflow\Eloquent\Definition;
use App\Project\Eloquent\Project;
use App\Project\Provider;

class ScreenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $screens = Provider::getScreenList($project_key, [ 'name', 'project_key', 'schema', 'description' ]);
        foreach ($screens as $screen)
        {
            $workflows = Definition::whereRaw([ 'screen_ids' => $screen->id ])
                ->orderBy('project_key', 'asc')
                ->get([ 'project_key', 'name' ])
                ->toArray();
            $screen->workflows = $workflows;

            if ($workflows)
            {
                $screen->is_used = true;
            }
            else
            {
                $screen->is_used = Type::where('screen_id', $screen->id)->exists(); 
            }

            $screen->workflows = array_filter($workflows, function($item) use($project_key) { 
                return $item['project_key'] === $project_key || $item['project_key'] === '$_sys_$';
            });
        }

        $fields = Field::Where([ 'project_key' => [ '$in' => [ $project_key, '$_sys_$' ] ] ])->orderBy('created_at', 'asc')->get(['name', 'key']);
        return Response()->json([ 'ecode' => 0, 'data' => $screens, 'options' => [ 'fields' => $fields ] ]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -12300);
        }

        $source_id = $request->input('source_id');
        if (isset($source_id) && $source_id)
        {
            $source_screen = Screen::find($source_id);
            $schema = $source_screen->schema;
            $field_ids = $source_screen->field_ids; 
        }
        else
        {
            $field_ids = $request->input('fields') ?: [];
            $required_field_ids = $request->input('required_fields') ?: [];
            // create screen schema
            $schema = $this->createSchema($field_ids, $required_field_ids);
        }

        $screen = Screen::create(['schema' => $schema, 'field_ids' => $field_ids, 'project_key' => $project_key ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $screen]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $screen = Screen::find($id);
        //if (!$screen || $project_key != $screen->project_key)
        //{
        //    throw new \UnexpectedValueException('the screen does not exist or is not in the project.', -10002);
        //}
        $screen->fields = $screen->schema;

        return Response()->json(['ecode' => 0, 'data' => $screen]);
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
                throw new \UnexpectedValueException('the name can not be empty.', -12300);
            }
        }
        $screen = Screen::find($id);
        if (!$screen || $project_key != $screen->project_key)
        {
            throw new \UnexpectedValueException('the screen does not exist or is not in the project.', -12301);
        }

        // when screen fields change, re-generate schema
        $field_ids = $request->input('fields');
        if (isset($field_ids))
        {
            $mapFields = [];
            foreach ($screen->schema as $field)
            {
                $mapFields[$field['_id']] = $field;
            }

            $new_schema = [];
            foreach ($field_ids as $field_id)
            {
                if (array_key_exists($field_id, $mapFields))
                {
                    $new_schema[] = $mapFields[$field_id];
                }
                else
                {
                    $new_schema[] = Field::Find($field_id, ['name', 'key', 'type', 'defaultValue', 'optionValues'])->toArray();
                }
            }
            $screen->schema = $new_schema;
            $screen->field_ids = $field_ids;
        }

        // when required fields change, re-generate schema
        $required_field_ids = $request->input('required_fields');
        if (isset($required_field_ids))
        {
            $new_schema = [];
            foreach ($screen->schema as $field)
            {
                if (in_array($field['_id'], $required_field_ids ?: []))
                {
                    $field['required'] = true;
                }
                else
                {
                    unset($field['required']);
                }
                $new_schema[] = $field;
            }
            $screen->schema = $new_schema;
        }

        $screen->fill($request->except(['project_key']))->save();
        return Response()->json(['ecode' => 0, 'data' => Screen::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $screen = Screen::find($id);
        if (!$screen || $project_key != $screen->project_key)
        {
            throw new \UnexpectedValueException('the screen does not exist or is not in the project.', -12301);
        }

        $isUsed = Type::where('screen_id', $id)->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the screen has been bound to type.', -12302);
        }

        $isUsed = Definition::whereRaw([ 'screen_ids' => $id ])->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the screen has been used in workflow.', -12303);
        }

        Screen::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * generate screen schema
     *
     * @param  array  $fields
     * @param  array  $required_fields
     * @return array
     */
    public function createSchema(array $field_ids, array $required_field_ids)
    {
        $schema = [];
        foreach ($field_ids as $field_id)
        {
            $new_field = Field::Find($field_id, ['name', 'key', 'type', 'applyToTypes', 'defaultValue', 'optionValues'])->toArray();
            if (!$new_field)
            {
                continue;
            }
            if (in_array($field_id, $required_field_ids))
            {
                $new_field['required'] = true;
            }
            $schema[] = $new_field;
        }
        return $schema;
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
            $types = Type::where('screen_id', $id)  
                ->where('project_key', '<>', '$_sys_$')
                ->where('project_key', $project->key)
                ->get([ 'id', 'name' ])
                ->toArray();

            $workflows = Definition::where('screen_ids', $id)
                ->where('project_key', '<>', '$_sys_$')
                ->where('project_key', $project->key)
                ->get([ 'id', 'name' ])
                ->toArray();

            if ($types || $workflows)
            {
                $tmp = [ 'key' => $project->key, 'name' => $project->name, 'status' => $project->status ];
                $tmp['types'] = $types ?: [];
                $tmp['workflows'] = $workflows ?: [];
                $res[] = $tmp;
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $res ]);
    }
}
