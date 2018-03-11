<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\Project\Eloquent\Epic;
use App\Project\Eloquent\Board;
use App\Project\Provider;
use DB;

class EpicController extends Controller
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
        $kanban_id = $request->input('kanban_id');
        $board = Board::find($kanban_id);

        $columns = isset($board->columns) ? $board->columns : [];

        $all_states = [];
        foreach ($columns as $column)
        {
            $all_states = array_merge($all_states, isset($column['states']) ? $column['states'] : []);
        }
        $last_column = array_pop($columns);
        $completed_states = isset($last_column['states']) ? $last_column['states'] : [];

        $epics = Epic::where([ 'project_key' => $project_key ])->orderBy('sn', 'asc')->get();
        foreach ($epics as $epic)
        {
            $epic->is_used = $this->isFieldUsedByIssue($project_key, 'epic', $epic->toArray());

            $completed_issue_cnt = $incompleted_issue_cnt = $inestimable_issue_cnt = 0;
            $issues = DB::collection('issue_' . $project_key)
                ->where('epic', $epic->id)
                ->where('del_flg', '<>', 1)
                ->get(['state']);
            foreach ($issues as $issue)
            {
                if (in_array($issue['state'], $completed_states))
                {
                    $completed_issue_cnt++;
                }
                else if (in_array($issue['state'], $all_states))
                {
                    $incompleted_issue_cnt++;
                }
                else
                {
                    $inestimable_issue_cnt++;
                }
            }
            $epic->completed = $completed_issue_cnt;
            $epic->incompleted = $incompleted_issue_cnt;
            $epic->inestimable = $inestimable_issue_cnt;
        }
            
        return Response()->json([ 'ecode' => 0, 'data' => $epics ]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -12000);
        }

        $bgColor = $request->input('bgColor');
        if (!$bgColor)
        {
            throw new \UnexpectedValueException('the bgColor can not be empty.', -12000);
        }

        if (Provider::isEpicExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('epic name cannot be repeated', -12001);
        }

        $epic = Epic::create([ 'project_key' => $project_key, 'sn' => time() ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $epic]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $epic = Epic::find($id);
        return Response()->json(['ecode' => 0, 'data' => $epic]);
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
                throw new \UnexpectedValueException('the name can not be empty.', -12000);
            }
        }

        $bgColor = $request->input('bgColor');
        if (isset($bgColor))
        {
            if (!$bgColor)
            {
                throw new \UnexpectedValueException('the bgColor can not be empty.', -12001);
            }
        }

        $epic = Epic::find($id);
        if (!$epic || $project_key != $epic->project_key)
        {
            throw new \UnexpectedValueException('the epic does not exist or is not in the project.', -12006);
        }

        if ($epic->name !== $name && Provider::isEpicExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('epic name cannot be repeated', -12001);
        }

        $epic->fill($request->except(['project_key']))->save();

        $new_epic = Epic::find($id);
        $new_epic->is_used = $this->isFieldUsedByIssue($project_key, 'epic', $new_epic->toArray());

        return Response()->json(['ecode' => 0, 'data' => $new_epic]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $epic = Epic::find($id);
        if (!$epic || $project_key != $epic->project_key)
        {
            throw new \UnexpectedValueException('the epic does not exist or is not in the project.', -12006);
        }

        $isUsed = $this->isFieldUsedByIssue($project_key, 'epic', $epic->toArray()); 
        if ($isUsed)
        {
            throw new \UnexpectedValueException('the epic has been used in issue.', -12007);
        }

        Epic::destroy($id);
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
        // set epic sort.
        $sequence_epics = $request->input('sequence');
        if (isset($sequence_epics))
        {
            $i = 1;
            foreach ($sequence_epics as $epic_id)
            {
                $epic = Epic::find($epic_id);
                if (!$epic || $epic->project_key != $project_key)
                {
                    continue;
                }
                $epic->sn = $i++;
                $epic->save();
            }
        }

        return Response()->json(['ecode' => 0, 'data' => $sequence_epics]);
    }
}
