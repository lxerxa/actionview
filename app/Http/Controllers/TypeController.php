<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Type;
use App\Workflow\Eloquent\Definition;
use App\Customization\Eloquent\Screen;
use App\Project\Provider;
use DB;

class TypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $types = Type::where([ 'project_key' => $project_key ])->orderBy('sn', 'asc')->get();
        foreach ($types as $type)
        {
            $type->is_used = $this->isFieldUsedByIssue($project_key, 'type', $type->toArray());
        }

        $screens = Provider::getScreenList($project_key, ['name']);
        $workflows = Provider::getWorkflowList($project_key, ['name']);
        $options = [ 'screens' => $screens, 'workflows' => $workflows ];

        return Response()->json([ 'ecode' => 0, 'data' => $types, 'options' => $options ]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -12000);
        }

        $abb = $request->input('abb');
        if (!$abb)
        {
            throw new \UnexpectedValueException('the abb can not be empty.', -12002);
        }
        
        // check screen_id
        $screen_id = $request->input('screen_id');
        if (!$screen_id)
        {
            throw new \UnexpectedValueException('the related screen can not be empty.', -12004);
        }
        //if (Screen::find($screen_id)->project_key != $project_key)
        //{
        //    throw new \UnexpectedValueException('the related screen is not exists.', -10002);
        //}

        // check workflow_id, workflow is too required? fix me
        $workflow_id = $request->input('workflow_id');
        if (!$workflow_id)
        {
            throw new \UnexpectedValueException('the related workflow can not be empty.', -12005);
        }

        if (Provider::isTypeExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('type name cannot be repeated', -12001);
        }

        if (Provider::isTypeAbbExisted($project_key, $abb))
        {
            throw new \UnexpectedValueException('type abb cannot be repeated', -12003);
        }

        //if (Definition::find($workflow_id)->project_key != $project_key)
        //{
        //    throw new \UnexpectedValueException('the related workflow is not exists.', -10002);
        //}

        $type = Type::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $type]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $type = Type::find($id);
        //if (!$type || $project_key != $type->project_key)
        //{
        //    throw new \UnexpectedValueException('the type does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $type]);
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
                throw new \UnexpectedValueException('the name can not be empty.', -12000);
            }
        }

        $abb = $request->input('abb');
        if (isset($abb))
        {
            if (!$abb)
            {
                throw new \UnexpectedValueException('the abb can not be empty.', -12001);
            }
        }

        // check screen_id
        $screen_id = $request->input('screen_id');
        if (isset($screen_id) && !$screen_id)
        {
            throw new \UnexpectedValueException('the related screen can not be empty.', -12004);
        }
        //if (Screen::find($screen_id)->project_key != $project_key)
        //{
        //    throw new \UnexpectedValueException('the related screen is not exists.', -10002);
        //}

        // check workflow_id
        $workflow_id = $request->input('workflow_id');
        if (isset($workflow_id) && !$workflow_id)
        {
            throw new \UnexpectedValueException('the related workflow can not be empty.', -12005);
        }
        //if (Definition::find($workflow_id)->project_key != $project_key)
        //{
        //    throw new \UnexpectedValueException('the related workflow is not exists.', -10002);
        //}

        $type = Type::find($id);
        if (!$type || $project_key != $type->project_key)
        {
            throw new \UnexpectedValueException('the type does not exist or is not in the project.', -12006);
        }

        if ($type->name !== $name && Provider::isTypeExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('type name cannot be repeated', -12001);
        }

        $abb = $request->input('abb');
        if ($type->abb !== $abb && Provider::isTypeAbbExisted($project_key, $abb))
        {
            throw new \UnexpectedValueException('type abb cannot be repeated', -12003);
        }

        $defaults = [];
        $disabled = $request->input('disabled');
        if (isset($disabled) && $type->default === true)
        {
            $defaults['default'] = false; 
        }

        $type->fill($defaults + $request->except(['project_key', 'type']))->save();

        $new_type = Type::find($id);
        $new_type->is_used = $this->isFieldUsedByIssue($project_key, 'type', $new_type->toArray());

        return Response()->json(['ecode' => 0, 'data' => $new_type]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $type = Type::find($id);
        if (!$type || $project_key != $type->project_key)
        {
            throw new \UnexpectedValueException('the type does not exist or is not in the project.', -12006);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'type', $type->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the type has been used in issue.', -12007);
        }

        Type::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * update sort or defaultValue etc..
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handle(Request $request, $project_key)
    {
        // set type sort.
        $sequence_types = $request->input('sequence');
        if (isset($sequence_types))
        {
            $i = 1;
            foreach ($sequence_types as $type_id)
            {
                $type = Type::find($type_id);
                if (!$type || $type->project_key != $project_key)
                {
                    continue;
                }
                $type->sn = $i++;
                $type->save();
            }
        }

        // set default value
        $default_type_id = $request->input('defaultValue');
        if (isset($default_type_id))
        {
            $type = Type::find($default_type_id);
            if (!$type || $type->project_key != $project_key)
            {
                throw new \UnexpectedValueException('the type does not exist or is not in the project.', -12006);
            }

            $types = Type::where('project_key', $project_key)->get();
            foreach ($types as $type)
            {
                if ($type->id == $default_type_id)
                {
                    $type->default = true;
                    $type->save();
                }
                else if (isset($type->default))
                {
                    $type->unset('default');
                }
            }
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence_types ?: null, 'default' => $default_type_id ?: null ]]);
    }
}
