<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\System\Eloquent\UserSetting;
use Sentinel;

class MysettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        $data = [];

        $user = Sentinel::findById($this->user->id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not existed.', -10002);
        }
        $data['accounts'] = $user;

        $user_setting = UserSetting::where('user_id', $this->user->id)->first();
        if ($user_setting && isset($user_setting->notifications))
        {
            $data['notifications'] = $user_setting->notifications;
        }
        else
        {
            $data['notifications'] = [ 'mail_notify' => true ];
        }

        if ($user_setting && isset($user_setting->favorites))
        {
            $data['favorites'] = $user_setting->favorites;
        }

        return Response()->json([ 'ecode' => 0, 'data' => $data ]);
    }

    /**
     * reset the user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function resetPwd(Request $request)
    {
        $password = $request->input('password');
        if (!$password || trim($password) == '')
        {
            throw new \UnexpectedValueException('the old password can not be empty.', -10002);
        }

        $user = Sentinel::findById($this->user->id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not existed.', -10002);
        }

        $credentials = [ 'email' => $this->user->email, 'password' => $password ];
        $valid = Sentinel::validateCredentials($user, $credentials);
        if (!$valid)
        {
            throw new \UnexpectedValueException('the old password is not correct.', -10002);
        }

        $new_password = $request->input('new_password');
        if (!$new_password || trim($new_password) == '')
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10002);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => $new_password ]);
        if (!$valid) 
        {
            throw new \UnexpectedValueException('resetting the password fails.', -10002);
        }
        //$user->password = password_hash($new_password, PASSWORD_DEFAULT);
        Sentinel::update($user, [ 'password' => $new_password ]);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'accounts' => $user ] ]);
    }

    /**
     * update the user accounts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updAccounts(Request $request)
    {
        $user = Sentinel::findById($this->user->id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not existed.', -10002);
        }

        $first_name = $request->input('first_name');
        if (isset($first_name))
        {
            if (!$first_name || trim($first_name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
            $user->first_name = trim($first_name);
        }

        $department = $request->input('department');
        if (isset($department) && trim($department))
        {
            $user->department = trim($department);
        }

        $position = $request->input('position');
        if (isset($position) && trim($position))
        {
            $user->position = trim($position);
        }

        $user = Sentinel::update($user, [ 'id' => $this->user->id ]);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'accounts' => $user ] ]);
    }

    /**
     * set the user notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setNotifications(Request $request)
    {
        $notifications = $request->all();

        $user_setting = UserSetting::where('user_id', $this->user->id)->first();
        if ($user_setting)
        {
            $new_notifications = $user_setting->notifications;
            foreach ($notifications as $key => $value) 
            {
                $new_notifications[$key] = $value;
            }

            $user_setting->fill([ 'notifications' => $new_notifications ])->save();
        }
        else
        {
            UserSetting::create([ 'user_id' => $this->user->id, 'notifications' => $notifications ]);
        }

        $notifications = UserSetting::where('user_id', $this->user->id)->first()->notifications;

        return Response()->json([ 'ecode' => 0, 'data' => [ 'notifications' => $notifications ] ]);
    }

    /**
     * set the user favorite.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setFavorites(Request $request)
    {
        $favorites = $request->all();

        $user_setting = UserSetting::where(user_id, $this->user->id)->first();
        if ($user_setting)
        {
            $new_favorites = $user_setting->favorites;
            foreach ($favorites as $key => $value) 
            {
                $new_favorites[$key] = $value;
            }

            $user_setting->fill([ 'favorites' => $new_favorites ])->save();
        }
        else
        {
            UserSetting::create([ 'user_id' => $this->user->id, 'favorites' => $favorites ]);
        }

        $favorites = UserSetting::where(user_id, $this->user->id)->first()->favorites;

        return Response()->json([ 'ecode' => 0, 'data' => [ 'favorites' => $favorites ] ]);
    }
}
