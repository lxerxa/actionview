<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
//use App\Events\VersionEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Version;

class VersionController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:manage_project', [ 'except' => [ 'index' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $versions = Version::whereRaw([ 'project_key' => $project_key ])->orderBy('created_at', 'desc')->get();
        return Response()->json([ 'ecode' => 0, 'data' => $versions ]);
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

        if (Version::whereRaw([ 'name' => $name, 'project_key' => $project_key ])->exists())
        {
            throw new \UnexpectedValueException('version name cannot be repeated', -10002);
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -10002);
        }

        $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $version = Version::create([ 'project_key' => $project_key, 'creator' => $creator ] + $request->all());

        // trigger event of version added
        //Event::fire(new VersionEvent($project_key, $creator, [ 'event_key' => 'create_version', 'data' => $version->name ]));

        return Response()->json([ 'ecode' => 0, 'data' => $version ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $version = Version::find($id);
        return Response()->json(['ecode' => 0, 'data' => $version]);
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
        $version = Version::find($id);
        if (!$version || $version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -10002);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }

            if ($version->name !== $name && Version::whereRaw([ 'name' => $name, 'project_key' => $project_key ])->exists())
            {
                throw new \UnexpectedValueException('version name cannot be repeated', -10002);
            }
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -10002);
        }

        $version->fill(array_except($request->all(), [ 'creator', 'project_key' ]))->save();

        // trigger event of version edited
        //$cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        //Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'edit_version', 'data' => $request->all() ]));

        return Response()->json([ 'ecode' => 0, 'data' => Version::find($id) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $version = Version::find($id);
        if (!$version || $version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -10002);
        }

        Version::destroy($id);

        // trigger event of version edited
        //$cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        //Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'del_version', 'data' => $version->name ]));

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
