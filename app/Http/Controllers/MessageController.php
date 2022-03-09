<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB;

use Sentinel;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = DB::collection('message')->where('receiver', $this->user->id);

        $status = $request->input('status');
        if (isset($status) && $status)
        {
            $query->where('status', $status);
        }

        $offset_id = $request->input('offset_id');
        if (isset($offset_id))
        {
            $query = $query->where('_id', '<', $offset_id);
        }

        $query->orderBy('_id', 'desc');

        $limit = $request->input('limit');
        if (!isset($limit))
        {
            $limit = 30;
        }
        $query->take(intval($limit));

        $avatars = [];
        $messages = $query->get();
        foreach ($messages as $key => $message)
        {
            $user_id = $message['body']['user']['id'];
            if (!array_key_exists($user_id, $avatars))
            {
                $user = Sentinel::findById($user_id);
                $avatars[$user_id] = isset($user->avatar) ? $user->avatar : '';
            }
            $messages[$key]['body']['user']['avatar'] = $avatars[$user_id];
        }

        $unReadCount = DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->count();

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($messages), 'options' => [ 'current_time' => time(), 'unReadCount' => $unReadCount ] ]);
    }

    /**
     * set message status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setStatus(Request $request)
    {
        $table = 'message';

        $id = $request->input('id');
        $message = DB::collection($table)->find($id);
        if (!$message)
        {
            throw new \UnexpectedValueException('the message does not exist.', -16200);
        }

        if ($this->user->id != $message['receiver'])
        {
            throw new \UnexpectedValueException('the message does not belong to this user.', -16201);
        }

        $status = $request->input('status');
        if (!in_array($status, ['read' , 'pending']))
        {
            throw new \UnexpectedValueException('the message status has error.', -16202);
        }

        $updValues = [ 'status' => $status, 'updated_at' => time() ];
        DB::collection($table)
            ->where('_id', $id)
            ->where('receiver', $this->user->id)
            ->update($updValues);

        $unReadCount = DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->count();

        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id, 'status' => $status, 'unReadCount' => $unReadCount ] ]);
    }

    /**
     * check if has unread message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function check(Request $request)
    {
        $unReadCount = DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->count();

        return Response()->json([ 'ecode' => 0, 'data' => [ 'unReadCount' => $unReadCount ] ]);
    }

    /**
     * set all issues status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setAllStatus(Request $request)
    {
        $status = $request->input('status');
        if (!in_array($status, ['read' , 'pending']))
        {
            throw new \UnexpectedValueException('the message status has error.', -16202);
        }

        $updValues = [ 'status' => $status, 'updated_at' => time() ];
        DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->update($updValues);

        $unReadCount = DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->count();

        return Response()->json([ 'ecode' => 0, 'data' => [ 'unReadCount' => $unReadCount ] ]);
    }
}
