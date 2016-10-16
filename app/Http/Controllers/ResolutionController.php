<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

//use App\Events\ResolutionConfigChangeEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\ResolutionProperty;
use App\Project\Provider;

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
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        if (Provider::isResolutionExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('resolution name cannot be repeated', -10002);
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
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }

        $resolution = Resolution::find($id);
        if (!$resolution || $project_key != $resolution->project_key)
        {
            throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -10002);
        }

        if ($resolution->name !== $name && Provider::isResolutionExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('resolution name cannot be repeated', -10002);
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
            throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -10002);
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

        $resolutions = Provider::getResolutionList($project_key);
        return Response()->json(['ecode' => 0, 'data' => $resolutions]);
    }
}
