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
     * Set user avatar.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setAvatar(Request $request)
    {
        $basename = md5(microtime() . $this->user->id);
        $avatar_save_path = config('filesystems.disks.local.root', '/tmp') . '/avatar/';
        if (!is_dir($avatar_save_path))
        {
            @mkdir($avatar_save_path);
        }
        $filename = '/tmp/' . $basename;

        $data = $request->input('data');
        if (!$data)
        {
            throw new \UnexpectedValueException('the uploaded avatar file can not be empty.', -15006);
        }
        file_put_contents($filename, base64_decode($data));

        $fileinfo = getimagesize($filename);
        if ($fileinfo['mime'] == 'image/jpeg' || $fileinfo['mime'] == 'image/jpg' || $fileinfo['mime'] == 'image/png' || $fileinfo['mime'] == 'image/gif')
        {
            $size = getimagesize($filename);
            $width = $size[0]; $height = $size[1];
            $scale = $width < $height ? $height : $width;
            $thumbnails_width = floor(150 * $width / $scale);
            $thumbnails_height = floor(150 * $height / $scale);
            $thumbnails_filename = $filename . '_thumbnails';
            if ($scale <= 150)
            {
                @copy($filename, $thumbnails_filename);
            }
            else if ($fileinfo['mime'] == 'image/jpeg' || $fileinfo['mime'] == 'image/jpg')
            {
                $src_image = imagecreatefromjpeg($filename);
                $dst_image = imagecreatetruecolor($thumbnails_width, $thumbnails_height);
                imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
                imagejpeg($dst_image, $thumbnails_filename);
            }
            else if ($fileinfo['mime'] == 'image/png')
            {
                $src_image = imagecreatefrompng($filename);
                $dst_image = imagecreatetruecolor($thumbnails_width, $thumbnails_height);
                imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
                imagepng($dst_image, $thumbnails_filename);
            }
            else if ($fileinfo['mime'] == 'image/gif')
            {
                $src_image = imagecreatefromgif($filename);
                $dst_image = imagecreatetruecolor($thumbnails_width, $thumbnails_height);
                imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
                imagegif($dst_image, $thumbnails_filename);
            }
            else 
            {
                @copy($filename, $thumbnails_filename);
            }

            @rename($thumbnails_filename, $avatar_save_path . $basename);
        }
        else
        {
            throw new \UnexpectedValueException('the avatar file type has errors.', -15007);
        }

        $user = Sentinel::findById($this->user->id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not existed.', -15000);
        }
        $user->fill([ 'avatar' => $basename ])->save();

        return $this->show(); 
    }

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
            throw new \UnexpectedValueException('the user is not existed.', -15000);
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
        if (!$password)
        {
            throw new \UnexpectedValueException('the old password can not be empty.', -15001);
        }

        $user = Sentinel::findById($this->user->id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not existed.', -15000);
        }

        $credentials = [ 'email' => $this->user->email, 'password' => $password ];
        $valid = Sentinel::validateCredentials($user, $credentials);
        if (!$valid)
        {
            throw new \UnexpectedValueException('the old password is not correct.', -15002);
        }

        $new_password = $request->input('new_password');
        if (!$new_password)
        {
            throw new \UnexpectedValueException('the password can not be empty.', -15003);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => $new_password ]);
        if (!$valid) 
        {
            throw new \UnexpectedValueException('resetting the password fails.', -15004);
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
            throw new \UnexpectedValueException('the user is not existed.', -15000);
        }

        $first_name = $request->input('first_name');
        if (isset($first_name))
        {
            if (!$first_name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -15005);
            }
            $user->first_name = $first_name;
        }

        $department = $request->input('department');
        if (isset($department))
        {
            $user->department = $department;
        }

        $position = $request->input('position');
        if (isset($position))
        {
            $user->position = $position;
        }

        $bind_email = $request->input('bind_email');
        if (isset($bind_email))
        {
            $user->bind_email = $bind_email;
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
