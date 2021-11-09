<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\DelGroupEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Eloquent\Group;

use App\ActiveDirectory\Eloquent\Directory;

use Sentinel;
use Cartalyst\Sentinel\Users\EloquentUser;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin', [ 'only' => [ 'index', 'delMultiGroups' ] ]);
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
                ->where(function ($query) {
                    $query->where('principal.id', $this->user->id)
                        ->orWhere(function ($query) {
                            $query->where('public_scope', '3')->where('users', $this->user->id);
                        })
                        ->orWhere(function ($query) {
                            $query->where('public_scope', '<>', '2')->where('public_scope', '<>', '3');
                        });
                    })
                ->get([ 'name' ]);

        }

        return Response()->json([ 'ecode' => 0, 'data' => $groups ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function mygroup(Request $request)
    {
        $query = Group::where('name', '<>', '');

        if ($name = $request->input('name'))
        {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($scale = $request->input('scale'))
        {
            if ($scale == 'myprincipal')
            {
                $query->where('principal.id', $this->user->id);
            }
            else if ($scale == 'myjoin')
            {
                $query->where('users', $this->user->id);
            }
        }

        $query->where(function ($query) {
            $query->where('principal.id', $this->user->id)
                ->orWhere(function ($query) {
                    $query->where('public_scope', '<>', '2')->where('users', $this->user->id);
                });
                //->orWhere(function ($query) {
                //    $query->where('public_scope', '<>', '2')->where('public_scope', '<>', '3');
                //});
        });

        // get total
        $total = $query->count();

        $page_size = 30;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $groups = $query->get();

        foreach ($groups as $group)
        {
            $group->users = EloquentUser::find($group->users ?: []);
        }

        return Response()->json([ 'ecode' => 0, 'data' => $groups, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size ] ]);
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

        // public_scope 范围
        if ($public_scope = $request->input('public_scope'))
        {
            if ($public_scope == '1')
            {
                $query->where('public_scope', '<>', '2')->where('public_scope', '<>', '3');
            }
            else if ($public_scope == '2' || $public_scope == '3')
            {
                $query->where('public_scope', $public_scope);
            }
        }

        // get total
        $total = $query->count();

        $page_size = 30;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $groups = $query->get();

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
        $insValues = [];

        $name = $request->input('name');
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10200);
        }
        $insValues['name'] = $name;

        $principal = $request->input('principal');
        if (isset($principal) && $principal)
        {
            if ($principal == 'self')
            {
                $insValues['principal'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
            }
            else
            {
                $principal_info = Sentinel::findById($principal);
                if (!$principal_info)
                {
                    throw new \InvalidArgumentException('the user is not exists.', -10205);
                }
                $insValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' => $principal_info->email ];
            }
        }

        $public_scope = $request->input('public_scope'); 
        if ($public_scope)
        {
            if (!in_array($public_scope, ['1', '2', '3']))
            {
                throw new \UnexpectedValueException('the public scope value has error.', -10204);
            }
            $insValues['public_scope'] = $public_scope;
        }
        else
        {
            $insValues['public_scope'] = '1';
        }

        $description = $request->input('description'); 
        if (isset($description))
        {
            $insValues['description'] = $description;
        }

        $source_id = $request->input('source_id');
        if ($source_id)
        {
            $source_group = Group::find($source_id);
            if (!$source_group)
            {
                throw new \UnexpectedValueException('the group does not exist.', -10201);
            }
            $insValues['users'] = isset($source_group->users) && $source_group->users ? $source_group->users : [];
        }

        $group = Group::create($insValues);
        if ($group->users)
        {
            $group->users = EloquentUser::find($group->users ?: []);
        }

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
        $group = Group::find($id);
        if (!$group)
        {
            throw new \UnexpectedValueException('the group does not exist.', -10201);
        }

        if (isset($group->diectory) && $group->directory && $group->diectory != 'self')
        {
            throw new \UnexpectedValueException('the group come from external directroy.', -10203);
        }

        if (!(isset($group->principal) && isset($group->principal['id']) && $group->principal['id'] == $this->user->id) && !$this->user->hasAccess('sys_admin'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

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

        $principal = $request->input('principal');
        if (isset($principal))
        {
            if ($principal)
            {
                $principal_info = Sentinel::findById($principal);
                if (!$principal_info)
                {
                    throw new \InvalidArgumentException('the user is not exists.', -10205);
                }
                $updValues['principal'] = [ 'id' => $principal_info->id, 'name' => $principal_info->first_name, 'email' => $principal_info->email ];
            }
            else
            {
                $updValues['principal'] = '';
            }
        }

        $user_ids = $request->input('users');
        if (isset($user_ids))
        {
            $updValues['users'] = $user_ids ?: [];
        }

        $public_scope = $request->input('public_scope');
        if (isset($public_scope))
        {
            if (!in_array($public_scope, ['1', '2', '3']))
            {
                throw new \UnexpectedValueException('the public scope value has error.', -10204);
            }
            $updValues['public_scope'] = $public_scope;
        }

        $description = $request->input('description'); 
        if (isset($description))
        {
            $updValues['description'] = $description;
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

        if (!(isset($group->principal) && isset($group->principal['id']) && $group->principal['id'] == $this->user->id) && !$this->user->hasAccess('sys_admin'))
        {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
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
