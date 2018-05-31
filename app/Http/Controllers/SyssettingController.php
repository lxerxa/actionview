<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\System\Eloquent\SysSetting;
use Sentinel;

use Mail;
use Config;

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
        $syssetting = SysSetting::first()->toArray();
        if (isset($syssetting['mailserver']) && isset($syssetting['mailserver']['smtp']) && isset($syssetting['mailserver']['smtp']['password']))
        {
            unset($syssetting['mailserver']['smtp']['password']);
        }
        return Response()->json([ 'ecode' => 0, 'data' => $syssetting ]);
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

        $mailserver = isset($syssetting->mailserver) ? $syssetting->mailserver : [];
        $smtp = $request->input('smtp');
        if (isset($smtp))
        {
            $updValues['mailserver'] = array_merge($mailserver, [ 'smtp' => $smtp ]);
        }
        $mail_send = $request->input('mail_send');
        if (isset($mail_send))
        {
            $updValues['mailserver'] = array_merge($mailserver, [ 'send' => $mail_send ]);
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
     * @return void 
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
        if (!isset($pwd) || !$pwd)
        {
            throw new \UnexpectedValueException('the name cannot be empty.', -12200);
        }

        $syssetting = SysSetting::first();
        $syssetting->smtp = array_merge($syssetting->smtp, [ 'send_auth_pwd' => $pwd ]);
        $syssetting->save();

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
        if (!isset($to) || !$to)
        {
            throw new \UnexpectedValueException('the mail recipients cannot be empty.', -15050);
        }

        $subject = $request->input('subject');
        if (!isset($subject) || !$subject)
        {
            throw new \UnexpectedValueException('the mail subject cannot be empty.', -15051);
        }

        $syssetting = SysSetting::first()->toArray(); 
        if (!isset($syssetting['mailserver']) || !$syssetting['mailserver']
            || !isset($syssetting['mailserver']['send']) || !$syssetting['mailserver']['send']
            || !isset($syssetting['mailserver']['smtp']) || !$syssetting['mailserver']['smtp']
            || !isset($syssetting['mailserver']['send']['from']) || !$syssetting['mailserver']['send']['from']
            || !isset($syssetting['mailserver']['smtp']['host']) || !$syssetting['mailserver']['smtp']['host']
            || !isset($syssetting['mailserver']['smtp']['port']) || !$syssetting['mailserver']['smtp']['port']
            || !isset($syssetting['mailserver']['smtp']['username']) || !$syssetting['mailserver']['smtp']['username']
            || !isset($syssetting['mailserver']['smtp']['password']) || !$syssetting['mailserver']['smtp']['password'])
        {
            throw new \UnexpectedValueException('the mail server config params have error.', -15052);
        }

        Config::set('mail.from', $syssetting['mailserver']['send']['from']);
        Config::set('mail.host', $syssetting['mailserver']['smtp']['host']);
        Config::set('mail.port', $syssetting['mailserver']['smtp']['port']);
        Config::set('mail.encryption', isset($syssetting['mailserver']['smtp']['encryption']) && $syssetting['mailserver']['smtp']['encryption'] ? $syssetting['mailserver']['smtp']['encryption'] : null);
        Config::set('mail.username', $syssetting['mailserver']['smtp']['username']);
        Config::set('mail.password', $syssetting['mailserver']['smtp']['password']);

        $prefix = isset($syssetting['mailserver']['send']['prefix']) ? $syssetting['mailserver']['send']['prefix'] : 'ActionView';

        $contents = $request->input('contents') ?: '';
        $data = [ 'contents' => $contents ];

        $subject = '[' . $prefix . ']' . $subject;

        Mail::send('emails.test', $data, function($message) use($to, $subject) {
            $message->from(Config::get('mail.from'), 'master')
                ->to($to)
                ->subject($subject);
        });

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
