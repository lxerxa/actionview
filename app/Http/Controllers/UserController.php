<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\DelUserEvent;

use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel; 

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($s = $request->input('s'))
        {
            $users = EloquentUser::where('first_name', 'like', '%' . $s .  '%')->get([ 'first_name', 'last_name' ])->toArray();
            foreach ($users as $key => $user)
            {
                //$users[$key]['name'] = $user['first_name'] . (isset($user['last_name']) && $user['last_name'] ? ' ' . $user['last_name'] : '');
                $users[$key]['name'] = $user['first_name'];
                unset($users[$key]['first_name']);
                unset($users[$key]['last_name']);
            }
        }
        else
        {
            $users = EloquentUser::all(); 
        }
        return Response()->json([ 'ecode' => 0, 'data' => $users ]); 
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! $email = $request->input('email'))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10002);
        }
        if (! $password = $request->input('password'))
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10002);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('email has already existed.', -10002);
        }

        $user = Sentinel::register([ 'email' => $email, 'password' => $password ], true);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Response()->json([ 'ecode' => 0, 'data' => Sentinel::findById($id) ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $password = $request->input('password');
        if (isset($password))
        {
            if (!$password || trim($password) == '')
            {
                throw new \UnexpectedValueException('the password can not be empty.', -10002);
            }
        }

        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10002);
        }

        $user = Sentinel::update($user, $request->except('email'));
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10002);
        }

        $user->delete();
        Event::fire(new DelUserEvent($id));
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
