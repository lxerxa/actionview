<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Group;

use Sentinel;

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
            $query->where('email', 'like', '%' . $name . '%');
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
            throw new \UnexpectedValueException('the name can not be empty.', -12700);
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
        //if (!$role || $project_key != $role->project_key)
        //{
        //    throw new \UnexpectedValueException('the role does not exist or is not in the project.', -10002);
        //}
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
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12700);
            }
        }

        $group = Group::find($id);
        $group->fill($request->all())->save();

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
        Group::destroy($id);
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * set users.
     *
     * @param  string $project_key
     * @param  array $uids
     * @return array
     */
    public function setUsers($group_id)
    {
        $user_ids = $request->input('users');

        $data = Role::find($id);
        $data->users = $this->getUsers($project_key, $id);

        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
    }
}
