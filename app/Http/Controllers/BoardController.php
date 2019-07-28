<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Project\Eloquent\Board;
use App\Project\Eloquent\BoardRankMap;
use App\Project\Eloquent\AccessBoardLog;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\Epic;
use App\Project\Eloquent\Version;
use App\Project\Provider;

class BoardController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:manage_project', [ 'only' => [ 'store', 'update', 'destroy' ] ]);
        parent::__construct();
    }

    /**
     * get user accessed board list
     *
     * @param  string  $project_key
     * @return response 
     */
    public function index($project_key) {
        // get all boards
        $boards = Board::Where('project_key', $project_key)
            ->orderBy('_id', 'asc')
            ->get();

        $access_records = AccessBoardLog::where('project_key', $project_key)
            ->where('user_id', $this->user->id)
            ->orderBy('latest_access_time', 'desc')
            ->get();

        $list = [];
        $accessed_boards = [];
        foreach($access_records as $record)
        {
            foreach($boards as $board)
            {
                if ($board->id == $record->board_id)
                {
                    $accessed_boards[] = $record->board_id; 
                    break;
                }
            }
            if (in_array($record->board_id, $accessed_boards))
            {
                $list[] = $board;
            }
        }

        foreach ($boards as $board)
        {
            if (!in_array($board->id, $accessed_boards))
            {
                $list[] = $board;
            }
        }

        $sprints = Sprint::where('project_key', $project_key)
            ->whereIn('status', [ 'active', 'waiting' ])
            ->orderBy('no', 'asc')
            ->get();

        $epics = Epic::where('project_key', $project_key)
            ->orderBy('sn', 'asc')
            ->get(['bgColor', 'name']);

        $versions = Version::where('project_key', $project_key)
            ->orderBy('created_at', 'desc')
            ->get(['name']);

        $completed_sprint_num = Sprint::where('project_key', $project_key)
            ->where('status', 'completed')
            ->max('no');

        return Response()->json([ 'ecode' => 0, 'data' => $list, 'options' => [ 'epics' => $epics, 'sprints' => $sprints, 'versions' => $versions, 'completed_sprint_num' => $completed_sprint_num ] ]);

/*
$example = [ 
  'id' => '111',
  'name' => '1111111111',
  'type' => 'kanban',
  'query' => [ 'type' => [ '59af4ad51d41c85e9108a8a7' ], 'subtask' => true ],
  'last_access_time' => 11111111,
  'columns' => [
    [ 'no' => 1, 'name' => '待处理', 'states' => [ 'Open', 'Reopened' ] ],
    [ 'no' => 2, 'name' => '处理中', 'states' => [ 'In Progess' ] ],
    [ 'no' => 3, 'name' => '关闭', 'states' => [ 'Resolved', 'Closed' ] ]
  ],
  'filters' => [
    [ 'no' => 1, 'id' => '11111', 'name' => '111111', 'query' => [ 'updated_at' => '1m' ] ],
    [ 'no' => 2, 'id' => '22222', 'name' => '222222' ],
    [ 'no' => 3, 'id' => '33333', 'name' => '333333' ],
  ],
];
        return Response()->json([ 'ecode' => 0, 'data' => [ $example ] ]);
*/
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
            throw new \UnexpectedValueException('the name can not be empty.', -11600);
        }

        $type = $request->input('type');
        if (!$type || ($type != 'kanban' && $type != 'scrum'))
        {
            throw new \UnexpectedValueException('the type value has error.', -11608);
        }

        $columns = [ 
            [ 'no' => 1, 'name' => '开始', 'states' => [] ], 
            [ 'no' => 2, 'name' => '处理中', 'states' => [] ],
            [ 'no' => 3, 'name' => '完成', 'states' => [] ],
        ];
        $states = Provider::getStateOptions($project_key);
        foreach ($states as $state)
        {
            $state_val = $state['_id'];
            if ($state['category'] === 'new')
            {
                array_push($columns[0]['states'], $state_val);
            }
            else if ($state['category'] === 'inprogress')
            {
                array_push($columns[1]['states'], $state_val);
            }
            else if ($state['category'] === 'completed')
            {
                array_push($columns[2]['states'], $state_val);
            }
        }

        // only support for kanban type, fix me
        $board = Board::create([ 
            'project_key' => $project_key, 
            'query' => [ 'subtask' => true ], 
            'columns' => $columns ] + $request->all());

        return Response()->json(['ecode' => 0, 'data' => $board]);
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
        $board = Board::find($id);
        if (!$board || $project_key != $board->project_key)
        {
            throw new \UnexpectedValueException('the board does not exist or is not in the project.', -11601);
        }

        $updValues = [];
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -11600);
            }
            $updValues['name'] = $name;
        }

        $description = $request->input('description');
        if (isset($description))
        {
            $updValues['description'] = $description;
        }

        $query = $request->input('query');
        if (isset($query))
        {
            // defaultly display subtask issue
            $updValues['query'] = [ 'subtask' => true ] + $query;
        }

        $filters = $request->input('filters');
        if (isset($filters))
        {
            $updValues['filters'] = $filters;
        }

        $columns = $request->input('columns');
        if (isset($columns))
        {
            $updValues['columns'] = $columns;
        }

        $display_fields = $request->input('display_fields');
        if (isset($display_fields))
        {
            $updValues['display_fields'] = $display_fields ?: [];
        }

        //$subtask = $request->input('subtask');
        //if (isset($subtask))
        //{
        //    $updValues['subtask'] = $subtask;
        //}

        $board->fill($updValues)->save();
        return Response()->json(['ecode' => 0, 'data' => Board::find($id)]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $board = Board::find($id);
        //if (!$board || $project_key != $board->project_key)
        //{
        //    throw new \UnexpectedValueException('the board does not exist or is not in the project.', -10002);
        //}
        return Response()->json(['ecode' => 0, 'data' => $state]);
    }

    /**
     * rank the column issues 
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return void
     */
    public function setRank(Request $request, $project_key, $id)
    {
        $current = $request->input('current') ?: '';
        if (!$current)
        {
            throw new \UnexpectedValueException('the ranked issue not be empty.', -11603);
        }

        $up = $request->input('up') ?: -1;
        $down = $request->input('down') ?: -1;
        if ($up == -1 && $down == -1)
        {
            throw new \UnexpectedValueException('the ranked position can not be empty.', -11604);
        }

        $rankmap = BoardRankMap::where([ 'board_id' => $id ])->first();
        if (!$rankmap || !isset($rankmap->rank))
        {
            throw new \UnexpectedValueException('the rank list is not exist.', -11605);
        }

        $rank = $rankmap->rank;
        $curInd = array_search($current, $rank);
        if ($curInd === false)
        {
            throw new \UnexpectedValueException('the issue is not found in the rank list.', -11606);
        }

        $blocks = [ $current ];
        $subtasks = Provider::getChildrenByParentNo($project_key, $current);
        if ($subtasks)
        {
            $rankedSubtasks = array_intersect($rank, $subtasks);
            $blocks = array_merge($blocks, $rankedSubtasks);
        }

        // delete current issue from the rank
        array_splice($rank, $curInd, count($blocks));

        if ($up != -1)
        {
            $upInd = array_search($up, $rank);

            $subtasks = Provider::getChildrenByParentNo($project_key, $up);
            $intersects = array_intersect($rank, $subtasks);

            if ($upInd === false && !$intersects)
            {
                throw new \UnexpectedValueException('the ranked position is not found.', -11607);
            }
            else
            {
                $realUpInd = -1;
                if ($intersects)
                {
                    $realUpInd = array_search(array_pop($intersects), $rank);
                }
                else
                {
                    $realUpInd = $upInd;
                }
                // insert current issue into the rank
                array_splice($rank, $realUpInd + 1, 0, $blocks);
            }
        }
        else
        {
            $downInd = array_search($down, $rank);
            if ($downInd !== false)
            {
                // insert current issue into the rank
                array_splice($rank, $downInd, 0, $blocks);
            }
            else
            {
                $subtasks = Provider::getChildrenByParentNo($project_key, $down);
                $intersects = array_intersect($rank, $subtasks);

                if (!$intersects)
                {
                    throw new \UnexpectedValueException('the ranked position is not found.', -11607);
                }
                $realDownInd = array_search(array_shift($intersects), $rank); 
                // insert current issue into the rank
                array_splice($rank, $realDownInd, 0, $blocks);
            }
        } 

        $old_rank = BoardRankMap::where([ 'board_id' => $id ])->first(); 
        $old_rank && $old_rank->delete();

        BoardRankMap::create([ 'board_id' => $id, 'rank' => $rank ]);

        $rankmap = BoardRankMap::where([ 'board_id' => $id ])->first(); 
        return Response()->json([ 'ecode' => 0, 'data' => $rankmap ]);
    }

    /**
     * record user access board
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return void
     */
    public function recordAccess($project_key, $id) 
    {
        $record = AccessBoardLog::where([ 'board_id' => $id, 'user_id' => $this->user->id ])->first();
        $record && $record->delete();

        AccessBoardLog::create([ 
          'project_key' => $project_key, 
          'user_id' => $this->user->id, 
          'board_id' => $id, 
          'latest_access_time' => time() ]);
        return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $board = Board::find($id);
        if (!$board || $project_key != $board->project_key)
        {
            throw new \UnexpectedValueException('the board does not exist or is not in the project.', -11601);
        }

        // delete access log
        AccessBoardLog::where('board_id', $id)->delete();

        // delete board rank
        BoardRankMap::where('board_id', $id)->delete();

        Board::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
        
    }
}
