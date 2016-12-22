<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Sentinel;
use DB;
use App\Project\Provider;

class CommentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key, $issue_id)
    {
        $comments = DB::collection('comments_' . $project_key)->where('issue_id', $issue_id)->orderBy('created_at', 'desc')->get();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($comments) ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key, $issue_id)
    {
        $contents = $request->input('contents');
        if (!$contents || trim($contents) == '')
        {
            throw new \UnexpectedValueException('the contents can not be empty.', -10002);
        }

        $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'nameAndEmail' => $this->user->first_name . '(' . $this->user->email . ')' ];

        $table = 'comments_' . $project_key;

        $id = DB::collection($table)->insertGetId(array_only($request->all(), [ 'contents', 'atWho' ]) + [ 'issue_id' => $issue_id, 'creator' => $creator, 'created_at' => time() ]);

        $comments = DB::collection($table)->where('_id', $id)->first();
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($comments) ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $comments = DB::collection('comments_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($comments)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $issue_id, $id)
    {
        $contents = $request->input('contents');
        if (isset($contents))
        {
            if (!$contents || trim($contents) == '')
            {
                throw new \UnexpectedValueException('the contents can not be empty.', -10002);
            }
        }

        $table = 'comments_' . $project_key;
        $operation = $request->input('operation');
        if (isset($operation)) 
        {
            $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'nameAndEmail' => $this->user->first_name . '(' . $this->user->email . ')' ];
            $comments = DB::collection('comments_' . $project_key)->where('_id', $id)->first();
            if (!isset($comments['reply']) || !$comments['reply'])
            {
                $comments['reply'] = [];
            }
            if ($operation == 'addReply') 
            {
                $reply_id = md5(microtime() . $this->user->id); 
                array_push($comments['reply'], array_only($request->all(), [ 'contents', 'atWho', 'to' ]) + [ 'id' => $reply_id , 'creator' => $creator, 'created_at' => time() ]);
            } 
            else if ($operation == 'editReply')
            {
                $reply_id = $request->input('reply_id');
                if (!isset($reply_id) || !$reply_id)
                {
                    throw new \UnexpectedValueException('the reply id can not be empty.', -10002);
                }
                $index = $this->array_find([ 'id' => $reply_id ], $comments['reply']); 
                if ($index !== false) {
                    $comments['reply'][$index] = array_merge($comments['reply'][$index], [ 'updated_at' => time(), 'edited_flag' => 1 ] + array_only($request->all(), [ 'contents', 'atWho' ]));
                }
                else
                {
                    throw new \UnexpectedValueException('the reply does not exist', -10002);
                }
            }
            else if ($operation == 'delReply')
            {
                $reply_id = $request->input('reply_id');
                if (!isset($reply_id) || !$reply_id)
                {
                    throw new \UnexpectedValueException('the reply id can not be empty.', -10002);
                }
                $index = $this->array_find([ 'id' => $reply_id ], $comments['reply']); 
                if ($index !== false) {
                    array_splice($comments['reply'], $index, 1);
                }
                else
                {
                    throw new \UnexpectedValueException('the reply does not exist', -10002);
                }
            }
            DB::collection($table)->where('_id', $id)->update([ 'reply' => $comments['reply'] ]);
        }
        else
        {
            DB::collection($table)->where('_id', $id)->update([ 'updated_at' => time(), 'edited_flag' => 1 ] + array_only($request->all(), [ 'contents', 'atWho' ]) );
        }

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange(DB::collection($table)->find($id)) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $issue_id, $id)
    {
        $table = 'comments_' . $project_key;
        $comments = DB::collection($table)->find($id);
        if (!$comments)
        {
            throw new \UnexpectedValueException('the comments does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    public function array_find($needle, $haystack)
    {
        foreach($haystack as $key => $val)
        {
            if ($needle['id'] == $val['id'])
            {
                return $key;
            }
        }
        return false;
    }
}
