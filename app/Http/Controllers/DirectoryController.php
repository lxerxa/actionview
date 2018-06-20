<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\DelGroupEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Acl\Eloquent\Group;
use Cartalyst\Sentinel\Users\EloquentUser;
use App\ActiveDirectory\Eloquent\Directory;
use App\ActiveDirectory\LDAP;

class DirectoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin');
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return Response()->json([ 'ecode' => 0, 'data' => Directory::all() ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!($name = $request->input('name')) || !($name = trim($name)))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10200);
        }

        $configs = [];
        if (!($host = $request->input('host')) || !($host = trim($host)))
        {
            throw new \UnexpectedValueException('the host can not be empty.', -10200);
        }
        $configs['host'] = $host;

        if (!($port = $request->input('port')) || !($port = trim($port)))
        {
            throw new \UnexpectedValueException('the port can not be empty.', -10200);
        }
        $configs['port'] = intval($port);

        $configs['encryption'] = $request->input('encryption') ?: '';

        if (!($admin_username = $request->input('admin_username')) || !($admin_username = trim($admin_username)))
        {
            throw new \UnexpectedValueException('the username can not be empty.', -10200);
        }
        $configs['admin_username'] = $admin_username;

        if (!($admin_password = $request->input('admin_password')) || !($admin_password = trim($admin_password)))
        {
            throw new \UnexpectedValueException('the user password can not be empty.', -10200);
        }
        $configs['admin_password'] = $admin_password;

        if (!($base_dn = $request->input('base_dn')) || !($base_dn = trim($base_dn)))
        {
            throw new \UnexpectedValueException('the base_dn can not be empty.', -10200);
        }
        $configs['base_dn'] = $base_dn;

        $configs['additional_user_dn'] = $request->input('additional_user_dn') ?: '';
        $configs['additional_group_dn'] = $request->input('additional_group_dn') ?: '';

        if (!($user_object_class = $request->input('user_object_class')) || !($user_object_class = trim($user_object_class)))
        {
            throw new \UnexpectedValueException('the user object class can not be empty.', -10200);
        }
        $configs['user_object_class'] = $user_object_class;

        if (!($user_object_filter = $request->input('user_object_filter')) || !($user_object_filter = trim($user_object_filter)))
        {
            throw new \UnexpectedValueException('the user object filter can not be empty.', -10200);
        }
        $configs['user_object_filter'] = $user_object_filter;

        if (!($user_name_attr = $request->input('user_name_attr')) || !($user_name_attr = trim($user_name_attr)))
        {
            throw new \UnexpectedValueException('the user name attributte can not be empty.', -10200);
        }
        $configs['user_name_attr'] = $user_name_attr;

        if (!($user_email_attr = $request->input('user_email_attr')) || !($user_email_attr = trim($user_email_attr)))
        {
            throw new \UnexpectedValueException('the user email attributte can not be empty.', -10200);
        }
        $configs['user_email_attr'] = $user_email_attr;


        if (!($group_object_class = $request->input('group_object_class')) || !($group_object_class = trim($group_object_class)))
        {
            throw new \UnexpectedValueException('the group object class can not be empty.', -10200);
        }
        $configs['group_object_class'] = $group_object_class;

        if (!($group_object_filter = $request->input('group_object_filter')) || !($group_object_filter = trim($group_object_filter)))
        {
            throw new \UnexpectedValueException('the group object filter can not be empty.', -10200);
        }
        $configs['group_object_filter'] = $group_object_filter;

        if (!($group_name_attr = $request->input('group_name_attr')) || !($group_name_attr = trim($group_name_attr)))
        {
            throw new \UnexpectedValueException('the group name attributte can not be empty.', -10200);
        }
        $configs['group_name_attr'] = $group_name_attr;

        if (!($group_membership_attr = $request->input('group_membership_attr')) || !($group_membership_attr = trim($group_membership_attr)))
        {
            throw new \UnexpectedValueException('the group membership attributte can not be empty.', -10200);
        }
        $configs['group_membership_attr'] = $group_membership_attr;

        $directory = Directroy::create([ 'name' => $name, 'type' => 'OpenLDAP', 'invalid_flag' => 0, 'configs' => $configs ]);
        return Response()->json([ 'ecode' => 0, 'data' => $directory ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10201);
        }
        return Response()->json([ 'ecode' => 0, 'data' => $directory ]);
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
            if (!($name = trim($name)))
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10200);
            }
            $updValues['name'] = $name;
        }

        $configs = [];

        $host = $request->input('host');
        if (isset($host))
        {
            if (!($host = trim($host)))
            {
                throw new \UnexpectedValueException('the host can not be empty.', -10200);
            }
            $configs['host'] = $host;
        }

        $port = $request->input('port'); 
        if (isset($port))
        {
            if (!($port = trim($port)))
            {
                throw new \UnexpectedValueException('the port can not be empty.', -10200);
            }
            $configs['port'] = intval($port);
        }

        $encryption = $request->input('encryption');
        if (isset($encryption))
        {
            $configs['encryption'] = $encryption;
        }

        $admin_username = $request->input('admin_username');
        if (isset($admin_username))
        {
            if (!($admin_username = trim($admin_username)))
            {
                throw new \UnexpectedValueException('the username can not be empty.', -10200);
            }
            $configs['admin_username'] = $admin_username;
        }

        $admin_password = $request->input('admin_password');
        if (isset($admin_password))
        {
            if (!($admin_password = trim($admin_password)))
            {
                throw new \UnexpectedValueException('the user password can not be empty.', -10200);
            }
            $configs['admin_password'] = $admin_password;
        }

        $base_dn = $request->input('base_dn');
        if (isset($base_dn))
        {
            if (!($base_dn = trim($base_dn)))
            {
                throw new \UnexpectedValueException('the base_dn can not be empty.', -10200);
            }
            $configs['base_dn'] = $base_dn;
        }

        $additional_user_dn = $request->input('additional_user_dn');
        if (isset($additional_user_dn))
        {
            $configs['additional_user_dn'] = trim($additional_user_dn);
        }

        $additional_group_dn = $request->input('additional_group_dn');
        if (isset($additional_group_dn))
        {
            $configs['additional_group_dn'] = trim($additional_group_dn);
        }

        $user_object_class = $request->input('user_object_class');
        if (isset($user_object_class))
        {
            if (!($user_object_class = trim($user_object_class)))
            {
                throw new \UnexpectedValueException('the user object class can not be empty.', -10200);
            }
            $configs['user_object_class'] = $user_object_class;
        }

        $user_object_filter = $request->input('user_object_filter');
        if (isset($user_object_filter))
        {
            if (!($user_object_filter = trim($user_object_filter)))
            {
                throw new \UnexpectedValueException('the user object filter can not be empty.', -10200);
            }
            $configs['user_object_filter'] = $user_object_filter;
        }

        $user_name_attr = $request->input('user_name_attr');
        if (isset($user_name_attr))
        {
            if (!($user_name_attr = trim($user_name_attr)))
            {
                throw new \UnexpectedValueException('the user name attributte can not be empty.', -10200);
            }
            $configs['user_name_attr'] = $user_name_attr;
        }

        $user_email_attr = $request->input('user_email_attr');
        if (isset($user_email_attr))
        {
            if (!($user_email_attr = trim($user_email_attr)))
            {
                throw new \UnexpectedValueException('the user email attributte can not be empty.', -10200);
            }
            $configs['user_email_attr'] = $user_email_attr;
        }

        $group_object_class = $request->input('group_object_class');
        if (isset($group_object_class))
        {
            if (!($group_object_class = trim($group_object_class)))
            {
                throw new \UnexpectedValueException('the group object class can not be empty.', -10200);
            }
            $configs['group_object_class'] = $group_object_class;
        }

        $group_object_filter = $request->input('group_object_filter');
        if (isset($group_object_filter))
        {
            if (!($group_object_filter = trim($group_object_filter)))
            {
                throw new \UnexpectedValueException('the group object filter can not be empty.', -10200);
            }
            $configs['group_object_filter'] = $group_object_filter;
        }

        $group_name_attr = $request->input('group_name_attr');
        if (isset($group_name_attr))
        {
            if (!($group_name_attr = trim($group_name_attr)))
            {
                throw new \UnexpectedValueException('the group name attributte can not be empty.', -10200);
            }
            $configs['group_name_attr'] = $group_name_attr;
        }

        $group_membership_attr = $request->input('group_membership_attr');
        if (isset($group_membership_attr))
        {
            if (!($group_membership_attr = trim($group_membership_attr)))
            {
                throw new \UnexpectedValueException('the group membership attributte can not be empty.', -10200);
            }
            $configs['group_membership_attr'] = $group_membership_attr;
        }

        if ($configs)
        {
            $updValues['configs'] = $configs;
        }

        $invalid_flag = $request->input('invalid_flag');
        if (isset($invalid_flag))
        {
            $updValues['invalid_flag'] = intval($invalid_flag);
        }
        
        $directory = Directory::find($id);
        $directory->fill($updValues)->save();

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
        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10201);
        }

        // delete the related groups
        $groups = Group::where('directory', $id)->get();
        foreach ($groups as $group)
        {
            $group->delete();
            Event::fire(new DelGroupEvent($group->id));
        }

        $users = EloquentUser::where('directory', $id)->get();
        foreach ($users as $user)
        {
            $user->delete();
            Event::fire(new DelUserEvent($user->id));
        }

        Directory::destroy($id);
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * test the ldap.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function test($id) {
        //$directory = Directory::find($id);
        //if (!$directory)
        //{
        //    throw new \UnexpectedValueException('the directory does not exist.', -10201);
        //}

        //$configs = [
        //    'default' => $directory->configs
        //];

        //$ret = LDAP::test($configs);
        $ret = LDAP::test([]);
        return Response()->json([ 'ecode' => 0, 'data' => $ret ]);
    }

    /**
     * test the ldap.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sync($id) {
        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10201);
        }

        $configs = [
            'default' => $directory->configs
        ];

        $ret = LDAP::sync($configs);
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }
}
