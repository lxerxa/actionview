<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\System\Eloquent\SysSetting;
use Sentinel;

class SyssettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin');
        parent::__construct();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        return Response()->json([ 'ecode' => 0, 'data' => SysSetting::first() ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $syssetting = SysSetting::first();

        $updValues = [];
        $properties = $request->input('properties');
        if (isset($properties))
        {
            $updValues['properties'] = $properties;
        }

        $smtp = $request->input('smtp');
        if (isset($smtp))
        {
            $updValues['smtp'] = $smtp;
        }

        $sysroles = $request->input('sysroles');
        if (isset($sysroles))
        {
            $updValues['sysroles'] = $sysroles;
            if (isset($syssetting->sysroles) && isset($syssetting->sysroles['sys_admin']))
            {
                $old_sys_admins = $syssetting->sysroles['sys_admin'];
            }
            else
            {
                $old_sys_admins = []; 
            }
            $old_sys_admin_ids = array_column($old_sys_admins, 'id');

            $new_sys_admins = isset($sysroles['sys_admin']) ? $sysroles['sys_admin'] : [];
            $new_sys_admin_ids = array_column($new_sys_admins, 'id'); 

            $added_user_ids = array_diff($new_sys_admin_ids, $old_sys_admin_ids) ?: [];
            $deleted_user_ids = array_diff($old_sys_admin_ids, $new_sys_admin_ids) ?: [];

            $this->handleUserPermission('sys_admin', $added_user_ids, $deleted_user_ids);
        }

        $syssetting->fill($updValues)->save();

        return Response()->json([ 'ecode' => 0, 'data' => SysSetting::first() ]);
    }

    /**
     * reset the smtp auth pwd.
     *
     * @param  string  $type
     * @param  array   $added_user_ids
     * @param  array   $deleted_user_ids
     * @return \Illuminate\Http\Response
     */
    public function handleUserPermission($permission, $added_user_ids, $deleted_user_ids)
    {
        foreach($added_user_ids as $uid)
        {
            $user = Sentinel::findById($uid); 
            $user->addPermission($permission)->save();
        }
        foreach($deleted_user_ids as $uid)
        {
            $user = Sentinel::findById($uid); 
            $user->removePermission($permission)->save();
        }
    }

    /**
     * reset the smtp auth pwd.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function resetPwd(Request $request)
    {
        $pwd = $request->input('send_auth_pwd');
        if (isset($pwd))
        {
            $syssetting = SysSetting::first();
            $syssetting->smtp = array_merge($syssetting->smtp, [ 'send_auth_pwd' => $pwd ]);
            $syssetting->save();
        }

        return Response()->json([ 'ecode' => 0, 'data' => SysSetting::first() ]);
    }

    /**
     * send the test mail 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendTestMail(Request $request)
    {
        $to = $request->input('to');
        $title = $request->input('title');
        $contents = $request->input('contents');

        return Response()->json([ 'ecode' => 0, 'data' => '' ]);
    }

    /**
     * add admin user, will be removed 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addAdmin(Request $request, $id)
    {
        $user = Sentinel::findById($id);
        $user->addPermission('sys_admin')->save();
        echo 'ok!'; exit;
    }
}
