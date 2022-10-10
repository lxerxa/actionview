<?php
namespace App\Sentinel;

use App\Sentinel\Hasher\Sha256Hasher;
use App\Sentinel\Eloquent\User;
use App\Sentinel\Eloquent\LoginErrLogs;
use App\Sentinel\Eloquent\Captcha;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

class Sentinel {

    public static function register($data) {
        if (isset($data['password']) && $data['password']) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        return User::create($data);
    }

    public static function update($user, $data) {
        if (isset($data['password']) && $data['password']) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $user->fill($data)->save();
        return $user;
    }

    public static function authenticate($credentials) {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return null;
        }

        $user = User::where('email', $credentials['email'])->first();
        if (isset($user->permissions) && is_string($user->permissions))
        {
            $user->permissions = json_decode($user->permissions, true);
        }

        return password_verify($credentials['password'], $user->password) ? $user : null;
    }

    public static function getUser() {
        $user = \Auth::user();
        if (isset($user->permissions) && is_string($user->permissions))
        {
            $user->permissions = json_decode($user->permissions, true);
        }
        return $user;
    }

    public static function findById($uid) {
        return User::find($uid);
    }

    public static function findByIds($uids) {
        return User::find($uids);
    }

    public static function findByCredentials($Credentials) {
        return User::where($Credentials)
            ->where('invalid_flag', '<>', 1)
            ->first();
    }

    public static function validateCredentials($user, $Credentials) {
        foreach ($Credentials as $key => $val) {
            if ($key == 'password') {
                if (!password_verify($val, $user->password)) {
                    return false;
                }
            } else if ($user->{$key} != $val) {
                return false;
            }
        }

        return true;
    }

    public static function createJWTToken($user) {
        return JWTAuth::fromUser($user);
    }

    public static function getJWTToken() {
        return JWTAuth::getToken();
    }

    public static function checkAndRefreshJWTTokenForWeb() {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return [ -1, null, null ];
            }

            $expiredAt = self::getTokenExpiredAt();
            if ($expiredAt - time() < env('JWT_TTL', 120) * 60 / 2) {
                try {
                    $refreshed = JWTAuth::refresh();
                    return [ 0, $user, $refreshed ];
                } catch (JWTException $e) {
                    return [ -1, null, null ];
                }
            }

            return [ 0, $user, null ];
        } catch (TokenExpiredException $e) {
            return [ -2, null, null ];
        } catch (TokenInvalidException $e) {
            return [ -3, null, null ];
        } catch (JWTException $e) {
            return [ -9, null, null ];
        }
    }

    public static function checkAndRefreshJWTTokenForMobile() {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return [ -1, null, null ];
            }
            return [ 0, $user, null ];
        } catch (TokenExpiredException $e) {
            try {
                $refreshed = JWTAuth::refresh();
                $user = JWTAuth::setToken($refreshed)->toUser();
                return [ 0, $user, $refreshed ];
            } catch (JWTException $e) {
                return [ -2, null, null ];
            }
        } catch (TokenInvalidException $e) {
            return [ -3, null, null ];
        } catch (JWTException $e) {
            return [ -9, null, null ];
        }
    }

    public static function checkAndRefreshJWTToken($source='mobile') {
        if ($source == 'mobile') {
            return self::checkAndRefreshJWTTokenForMobile();
        } else {
            return self::checkAndRefreshJWTTokenForWeb();
        }
    }

    public static function refreshJWTToken() {
        return JWTAuth::refresh();
    }

    public static function getTokenExpiredAt() {
        $payload = JWTAuth::parseToken()->getPayload();
        return $payload->get('exp');
    }

    public static function hasAccess($permission) {
        $user = self::getUser();
        if (isset($user->permissions) && $user->permissions) {
            $permissions = $user->permissions;
            return isset($permissions[$permission]) && $permissions[$permission];
        } else {
            return false;
        }
    }

    public static function logout() {
        try {
            //JWTAuth::invalidate(JWTAuth::getToken());
        } catch (Exception $e) {
        }
    }

    public static function addPermission($user, $permission) {
        $permissions = isset($user->permissions) ? $user->permissions : [];
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true);
        }
        $permissions[$permission] = true;
        $user->permissions = $permissions; 
        $user->save();
    }

    public static function removePermission($user, $permission) {
        $permissions = isset($user->permissions) ? $user->permissions : [];
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true);
        }

        if (isset($permissions[$permission])) {
            unset($permissions[$permission]);
        }
        $user->permissions = $permissions;
        $user->save();
    }

    public static function generateCaptcha($random)
    {
        Captcha::where('random', $random)->delete();

        $phrase = new PhraseBuilder;
        // 设置验证码位数
        $code = $phrase->build(4);
        // 生成验证码图片的Builder对象,配置相应属性
        $builder = new CaptchaBuilder($code, $phrase);
        // 设置背景颜色25,25,112
        $builder->setBackgroundColor(34, 0, 45);
        // 设置倾斜角度
        $builder->setMaxAngle(25);
        // 设置验证码后面最大行数
        $builder->setMaxBehindLines(5);
        // 设置验证码前面最大行数
        $builder->setMaxFrontLines(5);
        // 设置验证码颜色
        $builder->setTextColor(230, 81, 175);
        // 可以设置图片宽高及字体
        $builder->build($width = 100, $height = 40, $font = null);
        // 获取验证码的内容
        $phrase = $builder->getPhrase();
        // 保存验证码
	Captcha::create([ 
            'random' => $random, 
            'code' => strtolower($phrase), 
            'generated_at' => time() 
        ]);

        // 生成图片
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Type:image/jpeg');

        $builder->output();
    }

    public static function verifyCaptcha($random, $code)
    {
        $code = strtolower($code);

        $captcha = Captcha::where('random', $random)
            ->where('code', $code)
	    ->first();
        if (!$captcha)
        {
            return -1;
        }

        Captcha::where('random', $random)
            ->where('code', $code)
            ->delete();

        if (time() - $captcha->generated_at > 60)
        {
            return -2;
        }

	return 0;
    }

    public static function checkCaptchaRequired($credential)
    {
        $count = LoginErrLogs::where('email', $credential)
            ->where('recorded_at', '>', time() - 3600)
	    ->count();

	return $count >= 3;
    }

    public static function recordLoginError($email, $ip)
    {
        LoginErrLogs::create([ 
            'email' => $email, 
            'host' => $ip, 
            'recorded_at' => time() 
        ]);
    }
}
