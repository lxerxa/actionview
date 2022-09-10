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
        return User::create($data);
    }

    public static function update($user, $data) {
        return $user->fill($data)->save();
    }

    public static function authenticate($credentials) {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return null;
        }

        $user = User::where('email', $credentials['email'])->first();

        //password_hash($credentials['password'], PASSWORD_DEFAULT);

        return password_verify($credentials['password'], $user->password) ? $user : null;
    }

    public static function getUser() {
        return \Auth::user();
    }

    public static function findById($uid) {
        return User::find($uid);
    }

    public static function findByIds($uids) {
        return User::find($uids);
    }

    public static function findByCredentials($Credentials) {
        return User::where($Credentials)->first();
    }

    public static function validateCredentials($user, $Credentials) {
        $user2 = self::findByCredentials($Credentials);
        if (!$user2) {
            return false;
        }

        return $user->id == $user2->id;
    }

    public static function createJWTToken($user) {
        return JWTAuth::fromUser($user);
    }

    public static function getJWTToken() {
        return JWTAuth::getToken();
    }

    public static function checkJWTToken() {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return [ -1, null ];
            }
            return [ 0, $user ];
        } catch (TokenExpiredException $e) {
            return [ -2, null ];
        } catch (TokenInvalidException $e) {
            return [ -3, null ];
        } catch (JWTException $e) {
            return [ -9, null ];
        }
    }

    public static function refreshJWTToken() {
        return JWTAuth::refresh();
    }

    public static function getTokenExpiredAt() {
        $payload = JWTAuth::parseToken()->getPayload();
        return $payload->get('exp');
    }

    public static function logout() {
        try {
            if (JWTAuth::parseToken()->authenticate()) {
                self::refreshJWTToken();
            }
        } catch (Exception $e) {
        }
    }
}
