<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;

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
    public function index(Request $request, $project_key, $issue_id)
    {
        $sort = ($request->input('sort') === 'asc') ? 'asc' : 'desc';

        $comments = DB::collection('comments_' . $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('created_at', $sort)
            ->get();

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($comments), 'options' => [ 'current_time' => time() ] ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key, $issue_id)
    {
        if (!$this->isPermissionAllowed($project_key, 'add_comments')) 
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $contents = $request->input('contents');
        if (!$contents)
        {
            throw new \UnexpectedValueException('the contents can not be empty.', -11200);
        }

        $creator = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];

        $table = 'comments_' . $project_key;

        $id = DB::collection($table)->insertGetId(array_only($request->all(), [ 'contents', 'atWho' ]) + [ 'issue_id' => $issue_id, 'creator' => $creator, 'created_at' => time() ]);

        // trigger event of comments added
        Event::fire(new IssueEvent($project_key, $issue_id, $creator, [ 'event_key' => 'add_comments', 'data' => array_only($request->all(), [ 'contents', 'atWho' ]) ])); 

        $comments = DB::collection($table)->find($id);
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
        $comments = DB::collection('comments_' . $project_key)->find($id);
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
        $comments = DB::collection('comments_' . $project_key)->find($id);
        if (!$comments)
        {
            throw new \UnexpectedValueException('the comments does not exist or is not in the project.', -11201);
        }

        $contents = $request->input('contents');
        if (isset($contents))
        {
            if (!$contents)
            {
                throw new \UnexpectedValueException('the contents can not be empty.', -11200);
            }
        }
        // record the changed comments
        $changedComments = [];

        $table = 'comments_' . $project_key;
        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $operation = $request->input('operation');
        if (isset($operation)) 
        {
            if (!in_array($operation, [ 'addReply', 'editReply', 'delReply' ]))
            {
                throw new \UnexpectedValueException('the operation is incorrect value.', -11204);
            }
            if (!isset($comments['reply']) || !$comments['reply'])
            {
                $comments['reply'] = [];
            }

            if ($operation == 'addReply') 
            {
                if (!$this->isPermissionAllowed($project_key, 'add_comments')) 
                {
                    return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                }

                $reply_id = md5(microtime() . $this->user->id); 
                array_push($comments['reply'], array_only($request->all(), [ 'contents', 'atWho' ]) + [ 'id' => $reply_id , 'creator' => $user, 'created_at' => time() ]);
                $changedComments = array_only($request->all(), [ 'contents', 'atWho' ]) + [ 'to' => $comments['creator'] ];
            } 
            else if ($operation == 'editReply')
            {
                $reply_id = $request->input('reply_id');
                if (!isset($reply_id) || !$reply_id)
                {
                    throw new \UnexpectedValueException('the reply id can not be empty.', -11202);
                }
                $index = $this->array_find([ 'id' => $reply_id ], $comments['reply']); 
                if ($index !== false) 
                {
                    if (!$this->isPermissionAllowed($project_key, 'edit_comments') && !($comments['reply'][$index]['creator']['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'edit_self_comments')))
                    {
                        return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                    }
               
                    $comments['reply'][$index] = array_merge($comments['reply'][$index], [ 'updated_at' => time(), 'edited_flag' => 1 ] + array_only($request->all(), [ 'contents', 'atWho' ]));
                    $changedComments = array_only($comments['reply'][$index], [ 'contents', 'atWho' ]) + [ 'to' => $comments['creator'] ];
                }
                else
                {
                    throw new \UnexpectedValueException('the reply does not exist', -11203);
                }
            }
            else if ($operation == 'delReply')
            {
                $reply_id = $request->input('reply_id');
                if (!isset($reply_id) || !$reply_id)
                {
                    throw new \UnexpectedValueException('the reply id can not be empty.', -11202);
                }
                $index = $this->array_find([ 'id' => $reply_id ], $comments['reply']); 
                if ($index !== false) 
                {
                    if (!$this->isPermissionAllowed($project_key, 'delete_comments') && !($comments['reply'][$index]['creator']['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'delete_self_comments')))
                    {
                        return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
                    }

                    $changedComments = array_only($comments['reply'][$index], [ 'contents', 'atWho' ]) + [ 'to' => $comments['creator'] ];
                    array_splice($comments['reply'], $index, 1);
                }
                else
                {
                    throw new \UnexpectedValueException('the reply does not exist', -11203);
                }
            }
            DB::collection($table)->where('_id', $id)->update([ 'reply' => $comments['reply'] ]);
        }
        else
        {
            if (!$this->isPermissionAllowed($project_key, 'edit_comments') && !($comments['creator']['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'edit_self_comments')))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }

            DB::collection($table)->where('_id', $id)->update([ 'updated_at' => time(), 'edited_flag' => 1 ] + array_only($request->all(), [ 'contents', 'atWho' ]) );
            $changedComments = array_only($request->all(), [ 'contents', 'atWho' ]);
        }

        // trigger event of comments 
        $event_key = '';
        if (isset($operation))
        {
            $operation === 'addReply'   && $event_key = 'add_comments';
            $operation === 'editReply'  && $event_key = 'edit_comments';
            $operation === 'delReply'   && $event_key = 'del_comments';
        }
        else
        {
            $event_key = 'edit_comments';
        }
        Event::fire(new IssueEvent($project_key, $issue_id, $user, [ 'event_key' => $event_key, 'data' => $changedComments ]));

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
            throw new \UnexpectedValueException('the comments does not exist or is not in the project.', -11201);
        }

        if (!$this->isPermissionAllowed($project_key, 'manage_project') && !($comments['creator']['id'] == $this->user->id && $this->isPermissionAllowed($project_key, 'delete_self_comments'))) 
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        DB::collection($table)->where('_id', $id)->delete();

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        // trigger the event of del comments
        Event::fire(new IssueEvent($project_key, $issue_id, $user, [ 'event_key' => 'del_comments', 'data' => array_only($comments, [ 'contents', 'atWho' ]) ])); 

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * define the array function of searching for array object.
     *
     * @param  array  $needle
     * @param  array  $haystack
     * @return \Illuminate\Http\Response
     */
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
