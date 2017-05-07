<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\State;
//use App\System\Eloquent\SysSetting;

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
            throw new \UnexpectedValueException('email or password cannot be empty.', -10002);
        }

        if (strpos($email, '@') === false) 
        {
            //$setting = SysSetting::all();
            $email = $email . '@' . 'chinamobile.com'; // fix me 
        }

        $user = Sentinel::authenticate([ 'email' => $email, 'password' => $password ]);
        if ($user)
        {
            Sentinel::login($user);
            return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $user->toArray() ]]);
        }
        else 
        {
            return Response()->json([ 'ecode' => -10002, 'data' => [] ]);
        }
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
        return Response()->json([ 'ecode' => 0, 'data' => [ 'user' => $user ?: [] ] ]);
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
