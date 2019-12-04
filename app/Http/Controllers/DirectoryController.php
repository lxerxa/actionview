<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Acl\Eloquent\Group;
use Cartalyst\Sentinel\Users\EloquentUser;
use App\ActiveDirectory\Eloquent\Directory;
use App\ActiveDirectory\LDAP;

use App\Events\DelGroupEvent;
use App\Events\DelUserEvent;

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
        $directories =  Directory::all()->toArray();
        foreach($directories as $k => $d)
        {
            if (isset($d['configs']) && $d['configs'] && isset($d['configs']['admin_password']))
            {
                unset($directories[$k]['configs']['admin_password']);
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => $directories ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!($name = $request->input('name')))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10300);
        }

        $configs = [];
        if (!($host = $request->input('host')))
        {
            throw new \UnexpectedValueException('the host can not be empty.', -10301);
        }
        $configs['host'] = $host;

        if (!($port = $request->input('port')))
        {
            throw new \UnexpectedValueException('the port can not be empty.', -10302);
        }
        $configs['port'] = intval($port);

        $configs['encryption'] = $request->input('encryption') ?: '';

        if (!($admin_username = $request->input('admin_username')))
        {
            throw new \UnexpectedValueException('the username can not be empty.', -10303);
        }
        $configs['admin_username'] = $admin_username;

        if (!($admin_password = $request->input('admin_password')))
        {
            throw new \UnexpectedValueException('the user password can not be empty.', -10304);
        }
        $configs['admin_password'] = $admin_password;

        if (!($base_dn = $request->input('base_dn')))
        {
            throw new \UnexpectedValueException('the base_dn can not be empty.', -10305);
        }
        $configs['base_dn'] = $base_dn;

        $configs['additional_user_dn'] = $request->input('additional_user_dn') ?: '';
        $configs['additional_group_dn'] = $request->input('additional_group_dn') ?: '';

        if (!($user_object_class = $request->input('user_object_class')))
        {
            throw new \UnexpectedValueException('the user object class can not be empty.', -10306);
        }
        $configs['user_object_class'] = $user_object_class;

        if (!($user_object_filter = $request->input('user_object_filter')))
        {
            throw new \UnexpectedValueException('the user object filter can not be empty.', -10307);
        }
        $configs['user_object_filter'] = $user_object_filter;

        if (!($user_name_attr = $request->input('user_name_attr')))
        {
            throw new \UnexpectedValueException('the user name attributte can not be empty.', -10308);
        }
        $configs['user_name_attr'] = $user_name_attr;

        if (!($user_email_attr = $request->input('user_email_attr')))
        {
            throw new \UnexpectedValueException('the user email attributte can not be empty.', -10309);
        }
        $configs['user_email_attr'] = $user_email_attr;


        if (!($group_object_class = $request->input('group_object_class')))
        {
            throw new \UnexpectedValueException('the group object class can not be empty.', -10310);
        }
        $configs['group_object_class'] = $group_object_class;

        if (!($group_object_filter = $request->input('group_object_filter')))
        {
            throw new \UnexpectedValueException('the group object filter can not be empty.', -10311);
        }
        $configs['group_object_filter'] = $group_object_filter;

        if (!($group_name_attr = $request->input('group_name_attr')))
        {
            throw new \UnexpectedValueException('the group name attributte can not be empty.', -10312);
        }
        $configs['group_name_attr'] = $group_name_attr;

        if (!($group_membership_attr = $request->input('group_membership_attr')))
        {
            throw new \UnexpectedValueException('the group membership attributte can not be empty.', -10313);
        }
        $configs['group_membership_attr'] = $group_membership_attr;

        $directory = Directory::create([ 'name' => $name, 'type' => 'OpenLDAP', 'invalid_flag' => 0, 'configs' => $configs ]);
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
            throw new \UnexpectedValueException('the directory does not exist.', -10314);
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
        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10314);
        }

        $updValues = [];

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10300);
            }
            $updValues['name'] = $name;
        }

        $configs = [];

        $host = $request->input('host');
        if (isset($host))
        {
            if (!$host)
            {
                throw new \UnexpectedValueException('the host can not be empty.', -10301);
            }
            $configs['host'] = $host;
        }

        $port = $request->input('port'); 
        if (isset($port))
        {
            if (!$port)
            {
                throw new \UnexpectedValueException('the port can not be empty.', -10302);
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
            if (!$admin_username)
            {
                throw new \UnexpectedValueException('the username can not be empty.', -10303);
            }
            $configs['admin_username'] = $admin_username;
        }

        $admin_password = $request->input('admin_password');
        if (isset($admin_password))
        {
            if (!$admin_password)
            {
                throw new \UnexpectedValueException('the user password can not be empty.', -10304);
            }
            $configs['admin_password'] = $admin_password;
        }

        $base_dn = $request->input('base_dn');
        if (isset($base_dn))
        {
            if (!$base_dn)
            {
                throw new \UnexpectedValueException('the base_dn can not be empty.', -10305);
            }
            $configs['base_dn'] = $base_dn;
        }

        $additional_user_dn = $request->input('additional_user_dn');
        if (isset($additional_user_dn))
        {
            $configs['additional_user_dn'] = $additional_user_dn;
        }

        $additional_group_dn = $request->input('additional_group_dn');
        if (isset($additional_group_dn))
        {
            $configs['additional_group_dn'] = $additional_group_dn;
        }

        $user_object_class = $request->input('user_object_class');
        if (isset($user_object_class))
        {
            if (!$user_object_class)
            {
                throw new \UnexpectedValueException('the user object class can not be empty.', -10306);
            }
            $configs['user_object_class'] = $user_object_class;
        }

        $user_object_filter = $request->input('user_object_filter');
        if (isset($user_object_filter))
        {
            if (!$user_object_filter)
            {
                throw new \UnexpectedValueException('the user object filter can not be empty.', -10307);
            }
            $configs['user_object_filter'] = $user_object_filter;
        }

        $user_name_attr = $request->input('user_name_attr');
        if (isset($user_name_attr))
        {
            if (!$user_name_attr)
            {
                throw new \UnexpectedValueException('the user name attributte can not be empty.', -10308);
            }
            $configs['user_name_attr'] = $user_name_attr;
        }

        $user_email_attr = $request->input('user_email_attr');
        if (isset($user_email_attr))
        {
            if (!$user_email_attr)
            {
                throw new \UnexpectedValueException('the user email attributte can not be empty.', -10309);
            }
            $configs['user_email_attr'] = $user_email_attr;
        }

        $group_object_class = $request->input('group_object_class');
        if (isset($group_object_class))
        {
            if (!$group_object_class)
            {
                throw new \UnexpectedValueException('the group object class can not be empty.', -10310);
            }
            $configs['group_object_class'] = $group_object_class;
        }

        $group_object_filter = $request->input('group_object_filter');
        if (isset($group_object_filter))
        {
            if (!$group_object_filter)
            {
                throw new \UnexpectedValueException('the group object filter can not be empty.', -10311);
            }
            $configs['group_object_filter'] = $group_object_filter;
        }

        $group_name_attr = $request->input('group_name_attr');
        if (isset($group_name_attr))
        {
            if (!$group_name_attr)
            {
                throw new \UnexpectedValueException('the group name attributte can not be empty.', -10312);
            }
            $configs['group_name_attr'] = $group_name_attr;
        }

        $group_membership_attr = $request->input('group_membership_attr');
        if (isset($group_membership_attr))
        {
            if (!$group_membership_attr)
            {
                throw new \UnexpectedValueException('the group membership attributte can not be empty.', -10313);
            }
            $configs['group_membership_attr'] = $group_membership_attr;
        }

        if ($configs)
        {
            $updValues['configs'] = isset($directory->configs) ? array_merge($directory->configs ?: [], $configs) : $configs;
        }

        $invalid_flag = $request->input('invalid_flag');
        if (isset($invalid_flag))
        {
            $updValues['invalid_flag'] = intval($invalid_flag);
        }
        
        $directory->fill($updValues)->save();

        //if (isset($invalid_flag))
        //{
        //    EloquentUser::where('directory', $id)->update([ 'invalid_flag' => intval($invalid_flag) ]);
        //}

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
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10314);
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
    public function test($id) 
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10201);
        }

        $configs = [
            'default' => $directory->configs
        ];

        $ret = LDAP::test($configs);
        return Response()->json([ 'ecode' => 0, 'data' => array_pop($ret) ]);
    }

    /**
     * sync the users and group.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sync($id) 
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $directory = Directory::find($id);
        if (!$directory)
        {
            throw new \UnexpectedValueException('the directory does not exist.', -10314);
        }

        $configs = [
            $id => $directory->configs
        ];

        $ret = LDAP::sync($configs);
        $sync_info = array_pop($ret);
        if (!$sync_info['connect'])
        {
            throw new \UnexpectedValueException('the connect server failed.', -10315);
        }
        else if (!$sync_info['user'])
        {
            throw new \UnexpectedValueException('the user sync failed.', -10316);
        }
        else if (!$sync_info['group'])
        {
            throw new \UnexpectedValueException('the group sync failed.', -10317);
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $sync_info['user'], 'group' => $sync_info['group'] ] ]);
    }
}
