<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

//use App\Events\ResolutionConfigChangeEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\ResolutionProperty;
use App\Project\Eloquent\Project;
use App\Project\Provider;
use DB;

class ResolutionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $resolutions = Provider::getResolutionList($project_key);
        foreach ($resolutions as $key => $resolution)
        {
            $resolutions[$key]['is_used'] = $this->isFieldUsedByIssue($project_key, 'resolution', $resolution);
        }
        return Response()->json(['ecode' => 0, 'data' => $resolutions]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -12500);
        }

        if (Provider::isResolutionExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('resolution name cannot be repeated', -12501);
        }

        $resolution = Resolution::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        // trigger to change resolution field config
        //Event::fire(new ResolutionConfigChangeEvent($project_key));
        return Response()->json(['ecode' => 0, 'data' => $resolution]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $resolution = Resolution::find($id);
        //if (!$resolution || $project_key != $resolution->project_key)
        //{
        //    throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $resolution]);
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
        $resolution = Resolution::find($id);
        if (!$resolution || $project_key != $resolution->project_key)
        {
            throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -12502);
        }

        if (isset($resolution->key) && $resolution->key)
        {
            throw new \UnexpectedValueException('the resolution is built in the system.', -12504);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12500);
            }
            if ($resolution->name !== $name && Provider::isResolutionExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('resolution name cannot be repeated', -12501);
            }
        }

        $resolution->fill($request->except(['project_key']))->save();
        // trigger to change resolution field config
        //Event::fire(new ResolutionConfigChangeEvent($project_key));
        return Response()->json(['ecode' => 0, 'data' => Resolution::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $resolution = Resolution::find($id);
        if (!$resolution || $project_key != $resolution->project_key)
        {
            throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -12502);
        }

        if (isset($resolution->key) && in_array($resolution->key, [ 'Unresolved', 'Fixed' ]))
        {
            throw new \UnexpectedValueException('the resolution is built in the system.', -12504);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'resolution', $resolution->toArray());
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the resolution has been used in issue.', -12503);
        }

        Resolution::destroy($id);

        $resolution_property = ResolutionProperty::Where('project_key', $project_key)->first();
        if ($resolution_property)
        {
             $properties = [];
             if ($resolution_property->defaultValue == $id)
             {
                 $properties['defaultValue'] = '';
             }
             if ($resolution_property->sequence && in_array($id, $resolution_property->sequence))
             {
                 $sequence = [];
                 foreach ($resolution_property->sequence as $val)
                 {
                     if ($val == $id) { continue; }
                     $sequence[] = $val;
                 }
                 $properties['sequence'] = $sequence;
             }

             $resolution_property->fill($properties);
             $resolution_property->save();
        }

        // trigger to change resolution field config
        // Event::fire(new ResolutionConfigChangeEvent($project_key));
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
        if ($project_key === '$_sys_$')
        {
            return $this->handleSys($request, $project_key);
        }
        else
        {
            return $this->handleProject($request, $project_key);
        }
    }

    /**
     * update sort or defaultValue etc..
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handleProject(Request $request, $project_key)
    {
        $properties = [];
        // set resolution sort.
        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            $properties['sequence'] = $sequence;
        }

        // set default value
        $defaultValue = $request->input('defaultValue');
        if (isset($defaultValue))
        {
            $properties['defaultValue'] = $defaultValue;
        }

        $resolution_property = ResolutionProperty::Where('project_key', $project_key)->first();
        if ($resolution_property)
        {
             $resolution_property->fill($properties);
             $resolution_property->save();
        }
        else
        {
             ResolutionProperty::create([ 'project_key' => $project_key ] + $properties);
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence ?: null, 'default' => $defaultValue ?: null ]]);
    }

    /**
     * update sort or defaultValue etc..
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handleSys(Request $request, $project_key)
    {
        // set type sort.
        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            $i = 1;
            foreach ($sequence as $resolution_id)
            {
                $resolution = Resolution::find($resolution_id);
                if (!$resolution || $resolution->project_key != $project_key)
                {
                    continue;
                }
                $resolution->sn = $i++;
                $resolution->save();
            }
        }

        // set default value
        $default_resolution_id = $request->input('defaultValue');
        if (isset($default_resolution_id))
        {
            $resolution = Resolution::find($default_resolution_id);
            if (!$resolution || $resolution->project_key != $project_key)
            {
                throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -12502);
            }

            $resolutions = Resolution::where('project_key', $project_key)->get();
            foreach ($resolutions as $resolution)
            {
                if ($resolution->id == $default_resolution_id)
                {
                    $resolution->default = true;
                    $resolution->save();
                }
                else if (isset($resolution->default))
                {
                    $resolution->unset('default');
                }
            }
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence ?: null, 'default' => $default_resolution_id ?: null ]]);
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
            $count = DB::collection('issue_' . $project->key)
                ->where('resolution', $id)
                ->where('del_flg', '<>', 1)
                ->count();
            if ($count > 0)
            {
                $res[] = [ 'key' => $project->key, 'name' => $project->name, 'status' => $project->status, 'issue_count' => $count ];
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $res ]);
    }
}
