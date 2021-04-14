<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\Customization\Eloquent\StateProperty;
use App\Workflow\Eloquent\Definition;
use App\Project\Eloquent\Project;
use App\Project\Provider;
use DB;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $states = Provider::getStateList($project_key);
        foreach ($states as $key => $state)
        {
            $workflows = Definition::whereRaw([ 'state_ids' => isset($state['key']) ? $state['key'] : $state['_id'] ])
                ->orderBy('project_key', 'asc')
                ->get([ 'project_key', 'name' ])
                ->toArray();
            $states[$key]['workflows'] = $workflows;

            if ($workflows)
            {
                $states[$key]['is_used'] = true;
            }
            else
            {
                $states[$key]['is_used'] = $this->isFieldUsedByIssue($project_key, 'state', $state); 
            }

            $states[$key]['workflows'] = array_filter($workflows, function($item) use($project_key) { 
                return $item['project_key'] === $project_key || $item['project_key'] === '$_sys_$';
            });
        }
        return Response()->json(['ecode' => 0, 'data' => $states]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -12400);
        }

        $category = $request->input('category');
        if (!$category)
        {
            throw new \UnexpectedValueException('the category can not be empty.', -12405);
        }

        if (Provider::isStateExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('state name cannot be repeated', -12401);
        }

        $state = State::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $state]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $state = State::find($id);
        //if (!$state || $project_key != $state->project_key)
        //{
        //    throw new \UnexpectedValueException('the state does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $state]);
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
        $state = State::find($id);
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -12402);
        }

        if (isset($state->key) && $state->key)
        {
            throw new \UnexpectedValueException('the state is built in the system.', -12406);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12400);
            }
            if ($state->name !== $name && Provider::isStateExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('state name cannot be repeated', -12401);
            }
        }

        $category = $request->input('category');
        if (!$category)
        {
            throw new \UnexpectedValueException('the category can not be empty.', -12405);
        }

        $state->fill($request->except(['project_key']))->save();
        return Response()->json(['ecode' => 0, 'data' => State::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $state = State::find($id);
        if (!$state || $project_key != $state->project_key)
        {
            throw new \UnexpectedValueException('the state does not exist or is not in the project.', -12402);
        }

        if (isset($state->key) && $state->key)
        {
            throw new \UnexpectedValueException('the state is built in the system.', -12406);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'state', $state->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used in issue.', -12403);
        }

        $isUsed = Definition::whereRaw([ 'state_ids' => isset($state->key) ? $state->key : $id ])->exists();
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the state has been used in workflow.', -12404);
        }

        State::destroy($id);
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
        // set state sort.
        $sequence = $request->input('sequence');
        if (isset($sequence))
        {
            $properties['sequence'] = $sequence;
        }

        $state_property = StateProperty::Where('project_key', $project_key)->first();
        if ($state_property)
        {
             $state_property->fill($properties);
             $state_property->save();
        }
        else
        {
             StateProperty::create([ 'project_key' => $project_key ] + $properties);
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence ]]);
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
            foreach ($sequence as $state_id)
            {
                $state = State::find($state_id);
                if (!$state || $state->project_key != $project_key)
                {
                    continue;
                }
                $state->sn = $i++;
                $state->save();
            }
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'sequence' => $sequence ]]);
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
                ->where('state', $id)
                ->where('del_flg', '<>', 1)
                ->count();

            $workflows = Definition::where('state_ids', $id)
                ->where('project_key', '<>', '$_sys_$')
                ->where('project_key', $project->key)
                ->get([ 'id', 'name' ])
                ->toArray();

            if ($count > 0 || $workflows)
            {
                $tmp = [ 'key' => $project->key, 'name' => $project->name, 'status' => $project->status ];
                $tmp['issue_count'] = $count > 0 ? $count : 0;
                $tmp['workflows'] = $workflows ?: [];
                $res[] = $tmp;
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $res ]);
    }
}
