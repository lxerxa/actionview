<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

//use App\Events\PriorityConfigChangeEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\PriorityProperty;
use App\Project\Eloquent\Project;
use App\Project\Provider;
use DB;

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
        foreach ($priorities as $key => $priority)
        {
            $priorities[$key]['is_used'] = $this->isFieldUsedByIssue($project_key, 'priority', $priority); 
        }
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
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12600);
        }

        if (Provider::isPriorityExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('priority name cannot be repeated', -12601);
        }

        $priority = Priority::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        // trigger to change priority field config
        // Event::fire(new PriorityConfigChangeEvent($project_key));
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
        //if (!$priority || $project_key != $priority->project_key)
        //{
        //    throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -10002);
        //}
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
        $priority = Priority::find($id);
        if (!$priority || $project_key != $priority->project_key)
        {
            throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -12602);
        }

        if (isset($priority->key) && $priority->key)
        {
            throw new \UnexpectedValueException('the priority is built in the system.', -12604);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12600);
            }
            if ($priority->name !== $name && Provider::isPriorityExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('priority name cannot be repeated', -12601);
            }
        }

        $priority->fill($request->except(['project_key']))->save();
        // trigger to change priority field config
        // Event::fire(new PriorityConfigChangeEvent($project_key));
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
            throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -12602);
        }

        //if (isset($priority->key) && $priority->key)
        //{
        //    throw new \UnexpectedValueException('the priority is built in the system.', -12604);
        //}

        $isUsed = $this->isFieldUsedByIssue($project_key, 'priority', $priority->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the priority has been used in issue.', -12603);
        }

        Priority::destroy($id);

        $priority_property = PriorityProperty::Where('project_key', $project_key)->first();
        if ($priority_property)
        {
             $properties = [];
             if ($priority_property->defaultValue == $id)
             {
                 $properties['defaultValue'] = '';
             }
             if ($priority_property->sequence && in_array($id, $priority_property->sequence))
             {
                 $sequence = [];
                 foreach ($priority_property->sequence as $val)
                 {
                     if ($val == $id) { continue; }
                     $sequence[] = $val;
                 }
                 $properties['sequence'] = $sequence;
             }

             $priority_property->fill($properties);
             $priority_property->save();
        }

        // trigger to change priority field config
        // Event::fire(new PriorityConfigChangeEvent($project_key));
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
        // set priority sort.
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

        $priority_property = PriorityProperty::Where('project_key', $project_key)->first();
        if ($priority_property)
        {
             $priority_property->fill($properties);
             $priority_property->save();
        }
        else
        {
             PriorityProperty::create([ 'project_key' => $project_key ] + $properties);
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
            foreach ($sequence as $priority_id)
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
                throw new \UnexpectedValueException('the priority does not exist or is not in the project.', -12602);
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

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence ?: null, 'default' => $default_priority_id ?: null ]]);
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
                ->where('priority', $id)
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
