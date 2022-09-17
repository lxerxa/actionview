<?php
namespace App\Sentinel;

use App\Sentinel\Hasher\Sha256Hasher;
use App\Sentinel\Eloquent\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

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
            if ($expiredAt - time() < 3600) {
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
}
