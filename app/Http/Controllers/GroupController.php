<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\DelGroupEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Group;

use App\ActiveDirectory\Eloquent\Directory;

use Cartalyst\Sentinel\Users\EloquentUser;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin', [ 'except' => [ 'search' ] ]);
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
        $groups = [];
        if ($s)
        {
            $groups = Group::Where('name', 'like', '%' . $s .  '%')
                ->get([ 'name' ]);

        }
        return Response()->json([ 'ecode' => 0, 'data' => $groups ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Group::where('name', '<>', '');

        if ($name = $request->input('name'))
        {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($directory = $request->input('directory'))
        {
            $query->where('directory', $directory);
        }

        // get total
        $total = $query->count();

        $page_size = 30;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $groups = $query->get([ 'name', 'users', 'directory' ]);

        foreach ($groups as $group)
        {
            $group->users = EloquentUser::find($group->users ?: []);
        }

        return Response()->json([ 'ecode' => 0, 'data' => $groups, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size, 'directories' => Directory::all() ] ]);
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
        if (!$name)
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
        if (!$group)
        {
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }
        $group->users = EloquentUser::find($group->users);

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
            if (!$name)
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
        if (isset($group->diectory) && $group->directory && $group->diectory != 'self')
        {
            throw new \UnexpectedValueException('the group come from external directroy.', -10203);
        }

        $group->fill($updValues)->save();

        return $this->show($id);
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
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }
        if (isset($group->diectory) && $group->directory && $group->diectory != 'self')
        {
            throw new \UnexpectedValueException('the group come from external directroy.', -10203);
        }

        Group::destroy($id);
        Event::fire(new DelGroupEvent($id));
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * delete all selected groups.
     *
     * @return \Illuminate\Http\Response
     */
    public function delMultiGroups(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected groups cannot been empty.', -10201);
        }

        $deleted_ids = [];
        foreach ($ids as $id)
        {
            $group = Group::find($id);
            if ($group)
            {
                if (isset($group->diectory) && $group->directory && $group->diectory != 'self')
                {
                    continue;
                }
                $group->delete();
                Event::fire(new DelGroupEvent($id));
                $deleted_ids[] = $id;
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $deleted_ids ] ]);
    }
}
