<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\ResolutionConfigChangeEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Resolution;

class ResolutionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $resolutions = Resolution::where([ 'project_key' => $project_key ])->orderBy('sn', 'asc')->get();
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

        $resolution = Resolution::create($request->all() + [ 'project_key' => $project_key, 'sn' => time() ]);
        // trigger to change resolution field config
        Event::fire(new ResolutionConfigChangeEvent($project_key));
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
        if (!$resolution || $project_key != $resolution->project_key)
        {
            throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -10002);
        }
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

        $resolution->fill($request->except(['project_key']))->save();
        // trigger to change resolution field config
        Event::fire(new ResolutionConfigChangeEvent($project_key));
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
        // trigger to change resolution field config
        Event::fire(new ResolutionConfigChangeEvent($project_key));
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
        // set resolution sort.
        $sequence_resolutions = $request->input('sequence');
        if (isset($sequence_resolutions))
        {
            $i = 1;
            foreach ($sequence_resolutions as $resolution_id)
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
                throw new \UnexpectedValueException('the resolution does not exist or is not in the project.', -10002);
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
        // trigger to change resolution field config
        Event::fire(new ResolutionConfigChangeEvent($project_key));
    }
}
