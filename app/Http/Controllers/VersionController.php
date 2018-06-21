<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
//use App\Events\VersionEvent;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Version;
use App\Project\Provider;
use App\Customization\Eloquent\Field;

use DB;

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

        $version_fields = $this->getVersionFields($project_key);
        foreach ($versions as $version)
        {
            $unresolved_cnt = DB::collection('issue_' . $project_key)
                ->where('resolution', 'Unresolved')
                ->where('del_flg', '<>', 1)
                ->where('resolve_version', $version->id)
                ->count();

            $version->unresolved_cnt = $unresolved_cnt;
            $version->is_used = $this->isFieldUsedByIssue($project_key, 'version', $version->toArray(), $version_fields); 
        }
        return Response()->json([ 'ecode' => 0, 'data' => $versions ]);
    }

    /**
     * get all fields related with versiob 
     *
     * @return array 
     */
    public function getVersionFields($project_key)
    {
        $version_fields = [];
        // get all project fields
        $fields = Provider::getFieldList($project_key)->toArray();
        foreach ($fields as $field)
        {
            if ($field['type'] === 'SingleVersion' || $field['type'] === 'MultiVersion')
            {
                $version_fields[] = [ 'type' => $field['type'], 'key' => $field['key'] ];
            }
        }
        return $version_fields;
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
            throw new \UnexpectedValueException('the name can not be empty.', -11500);
        }

        if (Version::whereRaw([ 'name' => $name, 'project_key' => $project_key ])->exists())
        {
            throw new \UnexpectedValueException('version name cannot be repeated', -11501);
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -11502);
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
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -11503);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -11500);
            }

            if ($version->name !== $name && Version::whereRaw([ 'name' => $name, 'project_key' => $project_key ])->exists())
            {
                throw new \UnexpectedValueException('version name cannot be repeated', -11501);
            }
        }

        if ($request->input('start_time') && $request->input('end_time') && $request->input('start_time') > $request->input('end_time'))
        {
            throw new \UnexpectedValueException('start-time must less then end-time', -11502);
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
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -11503);
        }

        $version_fields = $this->getVersionFields($project_key);
        $isUsed = $this->isFieldUsedByIssue($project_key, 'version', $version->toArray(), $version_fields);
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the version has been used in issue.', -11504);
        }

        Version::destroy($id);

        // trigger event of version edited
        //$cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        //Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'del_version', 'data' => $version->name ]));

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
