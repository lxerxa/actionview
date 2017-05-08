<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\System\Eloquent\SysSetting;
use Sentinel;

class SyssettingController extends Controller
{
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
        }

        $syssetting = SysSetting::first();
        $syssetting->fill($updValues)->save();

        return Response()->json([ 'ecode' => 0, 'data' => SysSetting::first() ]);
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
}
