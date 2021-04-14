<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\ExternalUsers;
use App\Project\Eloquent\Project;
use App\Project\Provider;
use DB;

class ExternalUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $external_users = ExternalUsers::where('project_key', $project_key)->get();
        return Response()->json(['ecode' => 0, 'data' => $external_users]);
    }

    /**
     * handle the external user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, $project_key)
    {
        $data = [];

        $user = $request->input('user');
        if (!isset($user) || !$user)
        {
            throw new \UnexpectedValueException('the user cannot be empty.', -16000);
        }
        $data['user'] = $user;

        $mode = $request->input('mode');
        if (!isset($mode) || !$mode)
        {
            throw new \UnexpectedValueException('the mode cannot be empty.', -16001);
        }
        if (!in_array($mode, [ 'use', 'resetPwd', 'enable', 'disable' ]))
        {
            throw new \UnexpectedValueException('the mode value has error.', -16002);
        }
        $data['status'] = $mode == 'disable' ? 'disabled' : 'enabled';

        if ($mode == 'use' || $mode == 'resetPwd')
        {
            $pwd = $request->input('pwd');
            if (!isset($pwd) || !$pwd)
            {
                throw new \UnexpectedValueException('the password cannot be empty.', -16003);
            }
            $data['pwd'] = $pwd;
        }

        $data['project_key'] = $project_key;

        $external_user = ExternalUsers::where('project_key', $project_key)
            ->where('user', $user)
            ->first();
        if (!$external_user)
        {
            $external_user = new ExternalUsers;
        }

        $external_user->fill($data);
        $external_user->save();

        return Response()->json(['ecode' => 0, 'data' => $external_user]);
    }
}
