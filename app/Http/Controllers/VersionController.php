<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\VersionEvent;
use App\Events\IssueEvent;

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
    public function index(Request $request, $project_key)
    {
        $query = Version::where('project_key', $project_key);
        $total = $query->count();

        $query = $query->orderBy('status', 'desc')
            ->orderBy('released_time', 'desc')
            ->orderBy('end_time', 'desc')
            ->orderBy('created_at', 'desc');

        $page_size = $request->input('limit') ? intval($request->input('limit')) : 50;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);

        $versions = $query->get();

        $version_fields = $this->getVersionFields($project_key);
        foreach ($versions as $version)
        {
            $unresolved_cnt = DB::collection('issue_' . $project_key)
                ->where('resolution', 'Unresolved')
                ->where('del_flg', '<>', 1)
                ->where('resolve_version', $version->id)
                ->count();
            $version->unresolved_cnt = $unresolved_cnt;

            $all_cnt = DB::collection('issue_' . $project_key)
                ->where('del_flg', '<>', 1)
                ->where('resolve_version', $version->id)
                ->count();
            $version->all_cnt = $all_cnt;

            $version->is_used = $this->isFieldUsedByIssue($project_key, 'version', $version->toArray(), $version_fields);
        }

        $options = [ 'total' => $total, 'sizePerPage' => $page_size, 'current_time' => time() ];

        return Response()->json([ 'ecode' => 0, 'data' => $versions, 'options' => $options ]);
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
        if (!$name)
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
        $version = Version::create([ 'project_key' => $project_key, 'creator' => $creator, 'status' => 'unreleased' ] + $request->all());

        // trigger event of version added
        Event::fire(new VersionEvent($project_key, $creator, [ 'event_key' => 'create_version', 'data' => $version->toArray() ]));

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

        $unresolved_cnt = DB::collection('issue_' . $project_key)
            ->where('resolution', 'Unresolved')
            ->where('del_flg', '<>', 1)
            ->where('resolve_version', $version->id)
            ->count();
        $version->unresolved_cnt = $unresolved_cnt;

        $all_cnt = DB::collection('issue_' . $project_key)
            ->where('del_flg', '<>', 1)
            ->where('resolve_version', $version->id)
            ->count();
        $version->all_cnt = $all_cnt;

        if ($version->all_cnt > 0)
        {
            $version->is_used = true;
        }
        else
        {
            $version_fields = $this->getVersionFields($project_key);
            $version->is_used = $this->isFieldUsedByIssue($project_key, 'version', $version->toArray(), $version_fields);
        }

        return Response()->json(['ecode' => 0, 'data' => $version]);
    }

    /**
     * release the version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function release(Request $request, $project_key, $id)
    {
        $version = Version::find($id);
        if (!$version || $version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -11503);
        }

        $status = $request->input('status');
        if (isset($status))
        {
            $status_list = ['released', 'unreleased', 'archived'];
            if (!in_array($status, $status_list))
            {
                throw new \UnexpectedValueException('the status value has error.', -11505);
            }
        }
        else
        {
            throw new \UnexpectedValueException('the status value cannot be empty.', -11506);
        }

        $version->fill(['status' => $status, 'released_time' => time()])->save();

        $operate_flg = $request->input('operate_flg');
        if (isset($operate_flg) && $operate_flg === '1')
        {
            $swap_version = $request->input('swap_version');
            if (!isset($swap_version) || !$swap_version)
            {
                throw new \UnexpectedValueException('the swap version cannot be empty.', -11513);
            }
            $this->updIssueResolveVersion($project_key, $id, $swap_version);
        }

        if ($status === 'released')
        {
            $isSendMsg = $request->input('isSendMsg') && true;
            $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
            Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'release_version', 'isSendMsg' => $isSendMsg, 'data' => Version::find($id)->toArray() ]));
        }

        return $this->show($project_key, $id);
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
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -11500);
            }

            if ($version->name !== $name && Version::whereRaw([ 'name' => $name, 'project_key' => $project_key ])->exists())
            {
                throw new \UnexpectedValueException('version name cannot be repeated', -11501);
            }
        }

        $start_time = $request->input('start_time') ? $request->input('start_time') : $version->start_time;
        $end_time = $request->input('end_time') ? $request->input('end_time') : $version->end_time;
        if ($start_time > $end_time)
        {
            throw new \UnexpectedValueException('start-time must be less then end-time', -11502);
        }

        $updValues = [];
        $updValues = array_only($request->all(), [ 'name', 'start_time', 'end_time', 'description', 'status' ]);
        if (!$updValues)
        {
            return $this->show($project_key, $id);
        }

        $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();

        $version->fill($updValues)->save();

        Event::fire(new VersionEvent($project_key, $updValues['modifier'], [ 'event_key' => 'edit_version', 'data' => Version::find($id)->toArray() ]));

        return $this->show($project_key, $id);
    }

    /**
     * merge the version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function merge(Request $request, $project_key)
    {
        $source = $request->input('source');
        $source_version = Version::find($source);
        if (!$source_version || $source_version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the source version does not exist or is not in the project.', -11507);
        }
        if ($source_version->status === 'archived')
        {
            throw new \UnexpectedValueException('the source version has been archived.', -11508);
        }

        $dest = $request->input('dest');
        $dest_version = Version::find($dest);
        if (!$dest_version || $dest_version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the dest version does not exist or is not in the project.', -11509);
        }
        if ($dest_version->status === 'archived')
        {
            throw new \UnexpectedValueException('the source version has been archived.', -11510);
        }
        // update the issue related version info
        $this->updIssueVersion($project_key, $source, $dest);

        // delete the version
        Version::destroy($source);

        // trigger event of version edited
        $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'merge_version', 'data' => [ 'source' => $source_version->toArray(), 'dest' => $dest_version->toArray() ] ]));

        return $this->show($project_key, $dest);
    }

    /**
     * update the issues resolve version
     *
     * @param  string $project_key
     * @param  string $source
     * @param  string $dest
     * @return void 
     */
    public function updIssueResolveVersion($project_key, $source, $dest)
    {
        $issues = DB::collection('issue_' . $project_key)
            ->where('resolve_version', 'Unresolved')
            ->where('del_flg', '<>', 1)
            ->get();
        foreach ($issues as $issue)
        {
            $updValues['resolve_version'] = $dest;
            $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
            $updValues['updated_at'] = time();

            $issue_id = $issue['_id']->__toString();

            DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update($updValues);
            // add to histroy table
            $snap_id = Provider::snap2His($project_key, $issue_id, [], [ 'resolve_version' ]);
            // trigger event of issue edited
            Event::fire(new IssueEvent($project_key, $issue_id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
        }
    }

    /**
     * update the issues version
     *
     * @param  string $project_key
     * @param  string $source
     * @param  string $dest
     * @return void 
     */
    public function updIssueVersion($project_key, $source, $dest)
    {
        $version_fields = $this->getVersionFields($project_key);
        $issues = DB::collection('issue_' . $project_key)
            ->where(function ($query) use ($source, $version_fields) {
                foreach ($version_fields as $key => $vf)
                {
                    $query->orWhere($vf['key'], $source);
                }
            })
            ->where('del_flg', '<>', 1)
            ->get();

        foreach ($issues as $issue)
        {
            $updValues = [];
            foreach($version_fields as $key => $vf)
            {
                if (!isset($issue[$vf['key']]))
                {
                    continue;
                }
                if (is_string($issue[$vf['key']]))
                {
                    if ($issue[$vf['key']] === $source)
                    {
                        $updValues[$vf['key']] = $dest;
                    }
                }
                else if (is_array($issue[$vf['key']]))
                {
                    $updValues[$vf['key']] = array_values(array_filter(array_unique(str_replace($source, $dest, $issue[$vf['key']]))));
                }
            }

            if ($updValues)
            {
                $updFields = array_keys($updValues);

                $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
                $updValues['updated_at'] = time();

                $issue_id = $issue['_id']->__toString();

                DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update($updValues);
                // add to histroy table
                $snap_id = Provider::snap2His($project_key, $issue_id, [], $updFields);
                // trigger event of issue edited
                Event::fire(new IssueEvent($project_key, $issue_id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, $project_key, $id)
    {
        $version = Version::find($id);
        if (!$version || $version->project_key != $project_key)
        {
            throw new \UnexpectedValueException('the version does not exist or is not in the project.', -11503);
        }

        $operate_flg = $request->input('operate_flg');
        if (!isset($operate_flg) || $operate_flg === '0')
        {
            $version_fields = $this->getVersionFields($project_key);
            $is_used = $this->isFieldUsedByIssue($project_key, 'version', $version->toArray(), $version_fields);
            if ($is_used)
            {
                throw new \UnexpectedValueException('the version has been used by some issues.', -11511);
            }
        }
        else if ($operate_flg === '1')
        {
            $swap_version = $request->input('swap_version');
            if (!isset($swap_version) || !$swap_version)
            {
                throw new \UnexpectedValueException('the swap version cannot be empty.', -11513);
            } 

            $sversion = Version::find($swap_version);
            if (!$sversion || $sversion->project_key != $project_key)
            {
                throw new \UnexpectedValueException('the swap version does not exist or is not in the project.', -11514);
            }

            $this->updIssueVersion($project_key, $id, $swap_version);
        }
        else if ($operate_flg === '2') 
        {
            $this->updIssueVersion($project_key, $id, '');
        }
        else
        {
            throw new \UnexpectedValueException('the operation has error.', -11512);
        }

        Version::destroy($id);

        // trigger event of version edited
        $cur_user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new VersionEvent($project_key, $cur_user, [ 'event_key' => 'del_version', 'data' => $version->toArray() ]));

        if ($operate_flg === '1')
        {
            return $this->show($project_key, $request->input('swap_version'));
        }
        else
        {
            return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ]]);
        }
    }
}
