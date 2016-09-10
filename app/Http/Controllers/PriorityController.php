<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\PriorityConfigChangeEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Priority;
use App\Project\Provider;

class PriorityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $priorities = Provider::getPriorityList($project_key);
        return Response()->json(['ecode' => 0, 'data' => $priorities]);
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

        if (Provider::isPriorityExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('priority name cannot be repeated', -10002);
        }

        $priority = Priority::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        // trigger to change priority field config
        Event::fire(new PriorityConfigChangeEvent($project_key));
        return Response()->json(['ecode' => 0, 'data' => $priority]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $priority = Priority::find($id);
        if (!$priority || $project_key != $priority->project_key)
        {
            throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -10002);
        }
        return Response()->json(['ecode' => 0, 'data' => $priority]);
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
        $priority = Priority::find($id);
        if (!$priority || $project_key != $priority->project_key)
        {
            throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -10002);
        }

        if ($priority->name !== $name && Provider::isPriorityExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('priority name cannot be repeated', -10002);
        }

        $priority->fill($request->except(['project_key']))->save();
        // trigger to change priority field config
        Event::fire(new PriorityConfigChangeEvent($project_key));
        return Response()->json(['ecode' => 0, 'data' => Priority::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $priority = Priority::find($id);
        if (!$priority || $project_key != $priority->project_key)
        {
            throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -10002);
        }

        Priority::destroy($id);
        // trigger to change priority field config
        Event::fire(new PriorityConfigChangeEvent($project_key));
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
        // set priority sort.
        $sequence_priorities = $request->input('sequence');
        if (isset($sequence_priorities))
        {
            $i = 1;
            foreach ($sequence_priorities as $priority_id)
            {
                $priority = Priority::find($priority_id);
                if (!$priority || $priority->project_key != $project_key)
                {
                    continue;
                }
                $priority->sn = $i++;
                $priority->save();
            }
        }

        // set default value
        $default_priority_id = $request->input('defaultValue');
        if (isset($default_priority_id))
        {
            $priority = Priority::find($default_priority_id);
            if (!$priority || $priority->project_key != $project_key)
            {
                throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -10002);
            }

            $priorities = Priority::where('project_key', $project_key)->get();
            foreach ($priorities as $priority)
            {
                if ($priority->id == $default_priority_id)
                {
                    $priority->default = true;
                    $priority->save();
                }
                else if (isset($priority->default))
                {
                    $priority->unset('default');
                }
            }
        }
        // trigger to change priority field config
        Event::fire(new PriorityConfigChangeEvent($project_key));

        $priorities = Priority::whereRaw([ 'project_key' => $project_key ])->orderBy('sn', 'asc')->get();
        return Response()->json(['ecode' => 0, 'data' => $priorities]);
    }
}
