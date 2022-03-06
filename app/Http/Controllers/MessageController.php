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

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($messages), 'options' => [ 'current_time' => time() ] ]);
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
        $messge = DB::collection($table)->find($id);
        if (!$message)
        {
            throw new \UnexpectedValueException('the message does not exist.', -11103);
        }

        if ($this->user->id != $message['receiver'])
        {
            throw new \UnexpectedValueException('the message does not exist.', -11103);
        }

        $status = $request->input('status');
        if (!in_array($status, ['hasRead' , 'pending']))
        {
            throw new \UnexpectedValueException('the message does not exist.', -11103);
        }

        $updValues = [ 'status' => $status, 'updated_at' => time() ];
        DB::collection($table)->where('_id', $id)->update($updValues);

        return Response()->json([ 'ecode' => 0 ]);
    }

    /**
     * check if has unread message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function check(Request $request)
    {
        $hasUnread = DB::collection('message')
            ->where('receiver', $this->user->id)
            ->where('status', 'unRead')
            ->exists();

        return Response()->json([ 'ecode' => 0, 'data' => [ 'hasUnread' => $hasUnread ] ]);
    }
}
