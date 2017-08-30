<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\DelGroupEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Group;
use Cartalyst\Sentinel\Users\EloquentUser;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = Group::where('name', '<>', '');

        if ($name = $request->input('name'))
        {
            $query->where('name', 'like', '%' . $name . '%');
        }
        // get total
        $total = $query->count();

        $page_size = 30;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $groups = $query->get([ 'name', 'users' ]);

        return Response()->json([ 'ecode' => 0, 'data' => $groups ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10200);
        }

        $group = Group::create($request->all());
        return Response()->json([ 'ecode' => 0, 'data' => $group ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $group = Group::find($id);
<<<<<<< HEAD
        if (!$group)
        {
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }
        $group->users = EloquentUser::find($group->users);

=======
>>>>>>> 9906f3de55b99ee325b0058aafaad32e01bce0d4
        return Response()->json([ 'ecode' => 0, 'data' => $group ]);
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
        $updValues = [];
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10201);
            }
            $updValues['name'] = $name;
        }

        $user_ids = $request->input('users');
        if (isset($user_ids))
        {
            $updValues['users'] = $user_ids ?: [];
        }

        $group = Group::find($id);
        if (!$group)
        {
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }
        $group->fill($updValues)->save();

        $data = Group::find($id);
        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $group = Group::find($id);
        if (!$group)
        {
<<<<<<< HEAD
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }
    
        Group::destroy($id);
        Event::fire(new DelGroupEvent($id));
=======
            throw new \UnexpectedValueException('the group does not exist.', -10002);
        }

        Group::destroy($id);
        Event::fire(new DelUserEvent($id));
>>>>>>> 9906f3de55b99ee325b0058aafaad32e01bce0d4
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
