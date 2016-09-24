<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;

class VersionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $versions = DB::collection('version_' . $project_key)->orderBy('created_at', 'asc')->get();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($versions) ]);
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

        $table = 'version_' . $project_key;

        if (DB::collection($table)->where('name', $name)->exists())
        {
            throw new \UnexpectedValueException('version name cannot be repeated', -10002);
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -10002);
        }

        $id = DB::collection($table)->insertGetId(array_only($request->all(), ['name', 'start_time', 'end_time', 'description']));

        $version = DB::collection($table)->where('_id', $id)->first();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($version) ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $version = DB::collection('version_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($version)]);
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

        $table = 'version_' . $project_key;
        $version = DB::collection($table)->find($id);
        if (!$version)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -10002);
        }

        if ($version['name'] !== $name && DB::collection($table)->where('name', $name)->exists())
        {
            throw new \UnexpectedValueException('version name cannot be repeated', -10002);
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -10002);
        }

        DB::collection($table)->where('_id', $id)->update(array_only($request->all(), ['name', 'start_time', 'end_time', 'description']));

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
        $table = 'version_' . $project_key;
        $version = DB::collection($table)->find($id);
        if (!$version)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
