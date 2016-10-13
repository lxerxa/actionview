<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use MongoDB\BSON\UTCDateTime;
use DB;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $modules = DB::collection('module_' . $project_key)->orderBy('created_at', 'asc')->get();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($modules) ]);
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

        $table = 'module_' . $project_key;

        if (DB::collection($table)->where('name', $name)->exists())
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

        $id = DB::collection($table)->insertGetId(array_only($request->all(), ['name', 'defaultAssignee', 'description']) + [ 'principal' => $principal, 'created_at' => new UTCDateTime(time()*1000) ]);

        $module = DB::collection($table)->where('_id', $id)->first();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($module) ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $module = DB::collection('module_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($module)]);
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

        $table = 'module_' . $project_key;

        $module = DB::collection($table)->find($id);
        if (!$module)
        {
            throw new \UnexpectedValueException('the module does not exist or is not in the project.', -10002);
        }

        if ($module['name'] !== $name && DB::collection($table)->where('name', $name)->exists())
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

        DB::collection($table)->where('_id', $id)->update(array_only($request->all(), ['name', 'defaultAssignee', 'description']) + [ 'principal' => $principal ?: [], 'updated_at' => new UTCDateTime(time()*1000) ]);

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange(DB::collection($table)->find($id)) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $table = 'module_' . $project_key;
        $module = DB::collection($table)->find($id);
        if (!$module)
        {
            throw new \UnexpectedValueException('the module does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
