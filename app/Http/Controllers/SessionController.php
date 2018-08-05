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
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Cartalyst\Sentinel\Checkpoints\NotActivatedException;

use Sentinel;

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

        try {
            if (!($setting && isset($setting->properties) && isset($setting->properties['enable_login_protection']) && $setting->properties['enable_login_protection'] === 1))
            {
                Sentinel::removeCheckpoint('throttle');
            }

            $user = Sentinel::authenticate([ 'email' => $email, 'password' => $password ]);
        } catch (ThrottlingException $e) {
            // throttle error. 
            throw new Exception($e->getMessage(), -10004);
        } catch (NotActivatedException $e) {
            throw new Exception('the user is not activated.', -10005);
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

            Sentinel::login($user);
            $latest_access_project = $this->getLatestAccessProject($user->id);
            if ($latest_access_project)
            {
                $user->latest_access_project = $latest_access_project->key;
            }

            return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $user ] ]);
        }
        else 
        {
            return Response()->json([ 'ecode' => -10000, 'data' => [] ]);
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
}
