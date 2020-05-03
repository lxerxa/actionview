<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\IssueEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\Project\Eloquent\Labels;
use App\Project\Eloquent\Board;
use App\Project\Provider;
use DB;

class LabelsController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:manage_project');
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key)
    {
        $labels = Labels::where([ 'project_key' => $project_key ])->orderBy('_id', 'asc')->get();
        foreach ($labels as $key => $label)
        {
            $label->is_used = $this->isFieldUsedByIssue($project_key, 'labels', $label->toArray());

            $completed_issue_cnt = $incompleted_issue_cnt = 0;
            $unresolved_cnt = DB::collection('issue_' . $project_key)
                ->where('resolution', 'Unresolved')
                ->where('labels', $label['name'])
                ->where('del_flg', '<>', 1)
                ->count();
            $label->unresolved_cnt = $unresolved_cnt;

            $all_cnt = DB::collection('issue_' . $project_key)
                ->where('labels', $label['name'])
                ->where('del_flg', '<>', 1)
                ->count();
            $label->all_cnt = $all_cnt;
        }
            
        return Response()->json([ 'ecode' => 0, 'data' => $labels ]);
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
            throw new \UnexpectedValueException('the name can not be empty.', -11800);
        }

        if (Provider::isLabelsExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('label name cannot be repeated', -11802);
        }

        $label = Labels::create([ 'project_key' => $project_key ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $label]);
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
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -11800);
            }
        }

        $label = Labels::find($id);
        if (!$label || $project_key != $label->project_key)
        {
            throw new \UnexpectedValueException('the label does not exist or is not in the project.', -11803);
        }

        if ($label->name !== $name && Provider::isLabelsExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('label name cannot be repeated', -11802);
        }

        $label->fill($request->except(['project_key']))->save();

        return Response()->json(['ecode' => 0, 'data' => Labels::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, $project_key, $id)
    {
        $label = Labels::find($id);
        if (!$label || $project_key != $label->project_key)
        {
            throw new \UnexpectedValueException('the label does not exist or is not in the project.', -11803);
        }

        $operate_flg = $request->input('operate_flg');
        if (!isset($operate_flg) || $operate_flg === '0')
        {
            $is_used = $this->isFieldUsedByIssue($project_key, 'labels', $label->toArray());
            if ($is_used)
            {
                throw new \UnexpectedValueException('the label has been used by some issues.', -11804);
            }
        }
        else if ($operate_flg === '1')
        {
            $swap_label = $request->input('swap_label');
            if (!isset($swap_label) || !$swap_label)
            {
                throw new \UnexpectedValueException('the swap label cannot be empty.', -11806);
            }

            $slabel = Labels::find($swap_label);
            if (!$slabel || $project_key != $slabel->project_key)
            {
                throw new \UnexpectedValueException('the swap label does not exist or is not in the project.', -11807);
            }

            $this->updIssueLabels($project_key, $label->name, $slabel->name);
        }
        else if ($operate_flg === '2')
        {
            $this->updIssueLabels($project_key, $label->name, '');
        }
        else
        {
            throw new \UnexpectedValueException('the operation has error.', -11805);
        }

        Labels::destroy($id);

        return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ]]);

        //if ($operate_flg === '1')
        //{
        //    return $this->show($project_key, $request->input('swap_label'));
        //}
        //else
        //{
        //    return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ]]);
        //}
    }

    /**
     * update the issues label
     *
     * @param  array $issues
     * @param  string $source
     * @param  string $dest
     * @return \Illuminate\Http\Response
     */
    public function updIssueLabels($project_key, $source, $dest)
    {
        $issues = DB::collection('issue_' . $project_key)
            ->where('labels', $source)
            ->where('del_flg', '<>', 1)
            ->get();

        foreach ($issues as $issue)
        {
            $updValues = [];

            $newLabels = [];
            foreach ($issue['labels'] as $label)
            {
                if ($source == $label)
                {
                    if ($dest)
                    {
                        $newLabels[] = $dest;
                    }
                } 
                else 
                {
                    $newLabels[] = $label;
                }
            }
            $updValues['labels'] = array_values(array_unique($newLabels));

            $updValues['modifier'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
            $updValues['updated_at'] = time();

            $issue_id = $issue['_id']->__toString();

            DB::collection('issue_' . $project_key)->where('_id', $issue_id)->update($updValues);
            // add to histroy table
            $snap_id = Provider::snap2His($project_key, $issue_id, [], [ 'labels' ]);
            // trigger event of issue edited
            Event::fire(new IssueEvent($project_key, $issue_id, $updValues['modifier'], [ 'event_key' => 'edit_issue', 'snap_id' => $snap_id ]));
        }
    }
}
