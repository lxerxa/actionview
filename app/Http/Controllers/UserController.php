<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\DelUserEvent;
use App\Acl\Eloquent\Group;

use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel;
use Activation; 

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin', [ 'except' => [ 'register', 'search' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $s = $request->input('s');
        $users = [];
        if ($s)
        {
            $search_users = EloquentUser::Where('first_name', 'like', '%' . $s .  '%')
                                ->orWhere('email', 'like', '%' . $s .  '%')
                                ->get([ 'first_name', 'last_name', 'email' ]);

            $i = 0;
            foreach ($search_users as $key => $user)
            {
                if (Activation::completed($user) === false || $user->email === 'admin@action.view')
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
        }
        return Response()->json([ 'ecode' => 0, 'data' => $users ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = EloquentUser::where('email', '<>', '')->where('email', '<>', 'admin@action.view');

        if ($name = $request->input('name'))
        {
            $query->where(function ($query) use ($name) {
                $query->where('email', 'like', '%' . $name . '%')->orWhere('name', 'like', '%' . $name . '%');
            });
        }

        if ($group_id = $request->input('group'))
        {
            $group = Group::find($group_id);
            if ($group)
            {
                $query->whereIn('_id', $group->users ?: []);
            }
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
            $tmp['groups'] = array_column(Group::whereRaw([ 'users' => $user->id ])->get([ 'name' ])->toArray() ?: [], 'name');
 
            $tmp['status'] = Activation::completed($user) ? 'active' : 'unactivated';
            $users[] = $tmp;
        }
        return Response()->json([ 'ecode' => 0, 'data' => $users, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size, 'groups' => Group::all() ] ]); 
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
            throw new \UnexpectedValueException('the name can not be empty.', -10100);
        }

        if (!($email = $request->input('email')) || !($email = trim($email)))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10101);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('the email has already been registered.', -10102);
        }

        if (!$password = $request->input('password'))
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10103);
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
            throw new \UnexpectedValueException('the name can not be empty.', -10100);
        }

        if (!($email = $request->input('email')) || !($email = trim($email)))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10101);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('email has already existed.', -10102);
        }

        $phone = $request->input('phone') ? trim($request->input('phone')) : '';

        $user = Sentinel::register([ 'first_name' => $first_name, 'email' => $email, 'password' => 'actionview', 'phone' => $phone ], true);
        $user->status = Activation::completed($user) ? 'active' : 'unactivated';

        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Upload file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        if ($_FILES['file']['error'] > 0)
        {
            throw new \UnexpectedValueException('upload file errors.', -10104);
        }

        $fid = md5(microtime() . $_FILES['file']['name']);
        $filename = '/tmp/' . $fid;
        move_uploaded_file($_FILES['file']['tmp_name'], $filename);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'fid' => $fid ] ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function imports(Request $request)
    {
        if (!($fid = $request->input('fid')))
        {
            throw new \UnexpectedValueException('the user file can not be empty.', -10105);
        }

        $pattern = $request->input('pattern');
        if (!isset($pattern))
        {
            $pattern = '1';
        }

        $file = fopen("/tmp/$fid", 'r');
        $str = fread($file, 1024);
        $encode = mb_detect_encoding($str, [ 'ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5' ]);
        fclose($file);

        $file = fopen("/tmp/$fid", 'r');
        while ($user = fgetcsv($file))
        {
            if (count($user) < 2 || strpos($user[1], '@') === false)
            {
                continue;
            }

            $user[0] = mb_convert_encoding($user[0], 'UTF-8', $encode);

            $old_user = Sentinel::findByCredentials([ 'email' => $user[1] ]);
            if ($old_user)
            {
                if ($pattern == '1')
                {
                    continue;
                }
                else
                {
                    Sentinel::update($old_user, [ 'first_name' => $user[0], 'email' => $user[1], 'password' => 'actionview', 'phone' => isset($user[2]) ? $user[2] : '' ]); 
                }
            }
            else
            {
                Sentinel::register([ 'first_name' => $user[0], 'email' => $user[1], 'password' => 'actionview', 'phone' => isset($user[2]) ? $user[2] : '' ], true);
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ok' => true ] ]);
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
                throw new \UnexpectedValueException('the name can not be empty.', -10100);
            }
        }

        $email = $request->input('email');
        if (isset($email))
        {
            if (! $email = trim($email))
            {
                throw new \UnexpectedValueException('the email can not be empty.', -10101);
            }
            if ($user = Sentinel::findByCredentials([ 'email' => $email ]))
            {
                if ($user->id !== $id) {
                    throw new \InvalidArgumentException('email has already existed.', -10102);
                }
            }
        }

        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10106);
        }

        $valid = Sentinel::validForUpdate($user, array_only($request->all(), ['first_name', 'email', 'phone']));
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10107);
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
            throw new \UnexpectedValueException('the user does not exist.', -10106);
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
            throw new \InvalidArgumentException('the selected users cannot been empty.', -10108);
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
            throw new \UnexpectedValueException('the user does not exist.', -10106);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => 'actionview' ]);
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10107);
        }

        $user = Sentinel::update($user, [ 'password' => 'actionview' ]);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }
}
