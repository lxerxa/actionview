<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\DelUserEvent;

use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel;
use Activation; 

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
            $search_users = EloquentUser::Where('first_name', 'like', '%' . $s .  '%')
                                ->orWhere('email', 'like', '%' . $s .  '%')
                                ->get([ 'first_name', 'last_name', 'email' ]);

            $i = 0;
            $users = [];
            foreach ($search_users as $key => $user)
            {
                if (Activation::completed($user) === false)
                {
                    continue;
                }

                $users[$i]['id'] = $user->id;
                $users[$i]['name'] = $user->first_name ?: '';
                $users[$i]['email'] = $user->email;
                if (++$i >= 10)
                {
                    break;
                }
            }
            return Response()->json([ 'ecode' => 0, 'data' => $users ]);
        }
        else
        {
            $query = EloquentUser::Where('email', '<>', '');

            if ($name = $request->input('name'))
            {
                $query->where(function ($query) use ($name)
                {
                    $query->where('email', 'like', '%' . $name . '%')->orWhere('name', 'like', '%' . $name . '%');
                });
            }
            // get total
            $total = $query->count();

            $page_size = 30;
            $page = $request->input('page') ?: 1;
            $query = $query->skip($page_size * ($page - 1))->take($page_size);
            $all_users = $query->get([ 'first_name', 'last_name', 'email', 'phone' ]);

            $users = [];
            foreach ($all_users as $user)
            {
                $tmp = [];
                $tmp['id'] = $user->id;
                $tmp['first_name'] = $user->first_name;
                $tmp['email'] = $user->email;
                $tmp['phone'] = isset($user->phone) ? $user->phone : '';
                $tmp['status'] = Activation::completed($user) ? 'active' : 'unactivated';
                $users[] = $tmp;
            }
            return Response()->json([ 'ecode' => 0, 'data' => $users, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size ] ]); 
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        if (!($first_name = $request->input('first_name')) || !($first_name = trim($first_name)))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        if (!($email = $request->input('email')) || !($email = trim($email)))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10002);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('the email has already been registered.', -10002);
        }

        if (!$password = $request->input('password'))
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10002);
        }

        $user = Sentinel::register([ 'first_name' => $first_name, 'email' => $email, 'password' => $password ], true);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!($first_name = $request->input('first_name')) || !($first_name = trim($first_name)))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        if (!($email = $request->input('email')) || !($email = trim($email)))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10002);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('email has already existed.', -10002);
        }

        $phone = $request->input('phone') ? trim($request->input('phone')) : '';

        $user = Sentinel::register([ 'first_name' => $first_name, 'email' => $email, 'password' => 'actionview', 'phone' => $phone ], true);
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
        $first_name = $request->input('first_name');
        if (isset($first_name))
        {
            if (!$first_name = trim($first_name))
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }

        $email = $request->input('email');
        if (isset($email))
        {
            if (! $email = trim($email))
            {
                throw new \UnexpectedValueException('the email can not be empty.', -10002);
            }
            if ($user = Sentinel::findByCredentials([ 'email' => $email ]))
            {
                if ($user->id !== $id) {
                    throw new \InvalidArgumentException('email has already existed.', -10002);
                }
            }
        }

        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10002);
        }

        $valid = Sentinel::validForUpdate($user, array_only($request->all(), ['first_name', 'email', 'phone']));
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10002);
        }

        $user = Sentinel::update($user, array_only($request->all(), ['first_name', 'email', 'phone']));
        $user->status = Activation::completed($user) ? 'active' : 'unactivated';

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

    /**
     * delete all selected users.
     *
     * @return \Illuminate\Http\Response
     */
    public function delMultiUsers(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected users cannot been empty.', -10002);
        }

        $deleted_ids = [];
        foreach ($ids as $id)
        {
            $user = Sentinel::findById($id);
            if ($user)
            {
                $user->delete();
                Event::fire(new DelUserEvent($id));
                $deleted_ids[] = $id;
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $deleted_ids ] ]);
    }

    /**
     * reset the user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function renewPwd(Request $request, $id)
    {
        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10002);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => 'actionview' ]);
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10002);
        }

        $user = Sentinel::update($user, [ 'password' => 'actionview' ]);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }
}
