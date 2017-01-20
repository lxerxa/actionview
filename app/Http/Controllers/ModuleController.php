<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use App\Project\Provider;
use App\Project\Eloquent\Module;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $modules = Module::whereRaw([ 'project_key' => $project_key ])->orderBy('created_at', 'asc')->get();
        $users = Provider::getUserList($project_key);
        return Response()->json([ 'ecode' => 0, 'data' => $modules, 'options' => [ 'users' => $users ] ]);
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
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        if (Module::whereRaw([ 'name' => $name ])->exists())
        {
            throw new \UnexpectedValueException('module name cannot be repeated', -10002);
        }

        $principal = [];
        $principal_id = $request->input('principal');
        if (isset($principal_id))
        {
            $user_info = Sentinel::findById($principal_id);
            $principal = [ 'id' => $principal_id, 'name' => $user_info->first_name ];
        }

        $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];

        $module = Module::create([ 'project_key' => $project_key, 'principal' => $principal, 'creator' => $creator ] + $request->all());
        return Response()->json([ 'ecode' => 0, 'data' => $module ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $module = Module::find($id);
        return Response()->json(['ecode' => 0, 'data' => $module]);
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
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }

        $module = Module::find($id);
        if (!$module)
        {
            throw new \UnexpectedValueException('the module does not exist or is not in the project.', -10002);
        }

        if ($module->name !== $name && Module::whereRaw([ 'name' => $name ])->exists())
        {
            throw new \UnexpectedValueException('module name cannot be repeated', -10002);
        }

        $principal_id = $request->input('principal');
        if (isset($principal_id))
        {
            $user_info = Sentinel::findById($principal_id);
            $principal = [ 'id' => $principal_id, 'name' => $user_info->first_name ];
        }
        else
        {
            $principal = isset($module['principal']) ? $module['principal'] : [];
        }

        $module->fill([ 'principal' => $principal ] + array_except($request->all(), [ 'creator', 'project_key' ]))->save();
        return Response()->json([ 'ecode' => 0, 'data' => Module::find($id) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $module = Module::find($id);
        if (!$module || $project_key != $module->project_key)
        {
            throw new \UnexpectedValueException('the module does not exist or is not in the project.', -10002);
        }

        Module::destroy($id);

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
