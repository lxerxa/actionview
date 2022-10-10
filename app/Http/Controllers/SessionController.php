<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
use App\System\Eloquent\SysSetting;
use App\Project\Eloquent\AccessProjectLog;
use App\Project\Eloquent\Project;

use App\ActiveDirectory\Eloquent\Directory;
use App\ActiveDirectory\LDAP;

use Exception;
use App\Sentinel\Sentinel;

class SessionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        if (!$email || !$password)
        {
            throw new \UnexpectedValueException('email or password cannot be empty.', -10003);
        }

        $setting = SysSetting::first();
        if (strpos($email, '@') === false)
        {
            if ($setting && isset($setting->properties) && isset($setting->properties['login_mail_domain']))
            {
                $email = $email . '@' . $setting->properties['login_mail_domain'];
            }
        }

        // verify the captcha
        $random = $request->input('random');
        $captcha = $request->input('captcha');
        if ($random && $captcha)
        {
            $ret = Sentinel::verifyCaptcha($random, $captcha);
            if ($ret === -1)
            {
                return Response()->json([ 'ecode' => -10008, 'data' => [] ]);
            }
            else if ($ret === -2)
            {
                return Response()->json([ 'ecode' => -10009, 'data' => [] ]);
            }
        }
        else if (Sentinel::checkCaptchaRequired($email))
        {
            return Response()->json([ 'ecode' => -10007, 'data' => [ 'captcha_required' => true ] ]);
        }

        try {
            $user = Sentinel::authenticate([ 'email' => $email, 'password' => $password ]);
        } catch (Exception $e) {
            return Response()->json([ 'ecode' => -10000, 'data' => [] ]);
        }

        // ldap authenticate
        if (!$user)
        {
            $configs = [];
            $directories = Directory::where('type', 'OpenLDAP')
                ->where('invalid_flag', '<>', 1)
                ->get();
            foreach ($directories as $d)
            {
                $configs[$d->id] = $d->configs ?: [];
            }

            if ($configs)
            {
                $user = LDAP::attempt($configs, $email, $password);
            }
        }

        if ($user)
        {
            if ($user->invalid_flag == 1)
            {
                throw new Exception('the user is disabed.', -10006);
            }

            $latest_access_project = $this->getLatestAccessProject($user->id);
            if ($latest_access_project)
            {
                $user->latest_access_project = $latest_access_project->key;
            }

            $token = Sentinel::createJWTToken($user);
            return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $user, 'captcha_required' => false ] ])->withHeaders([ 'Authorization' => 'Bearer ' . $token ]);
        }
        else 
        {
            Sentinel::recordLoginError($email, $request->ip());
            return Response()->json([ 'ecode' => -10000, 'data' => [ 'captcha_required' => Sentinel::checkCaptchaRequired($email) ] ]);
        }
    }

    /**
     * get the latest project.
     *
     * @param  string  $uid
     * @return Object 
     */
    public function getLatestAccessProject($uid)
    {
        // get latest access project 
        $latest_access_project = AccessProjectLog::where('user_id', $uid)
            ->where('latest_access_time', '>', time() - 2 * 7 * 24 * 3600)
            ->orderBy('latest_access_time', 'desc')
            ->first();

        if ($latest_access_project)
        {
            $project = Project::where('key', $latest_access_project->project_key)->first();
            if ($project && $project->status === 'active')
            {
                return $project;
            }
        }
        return null;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getSess(Request $request)
    {
        $user = Sentinel::getUser();
        if ($user)
        {
            $latest_access_project = $this->getLatestAccessProject($user->id);
            if ($latest_access_project)
            {
                $user->latest_access_project = $latest_access_project->key;
            }
            return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $user ] ]);
        }
        else
        {
            return Response()->json([ 'ecode' => -10001, 'data' => [ 'user' => [] ] ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        Sentinel::logout();
        return Response()->json([ 'ecode' => 0, 'data' => [] ]);
    }

    /**
     * generate the captche.
     *
     * @param  \Illuminate\Http\Request  $request 
     * @return \Illuminate\Http\Response
     */
    public function getCaptcha(Request $request)
    {
        $random = $request->input('random');
        if (!$random)
        {
            return;
        }

        Sentinel::generateCaptcha($random);
    }
}
