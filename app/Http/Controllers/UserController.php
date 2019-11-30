<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\DelUserEvent;
use App\Acl\Eloquent\Group;

use App\ActiveDirectory\Eloquent\Directory;

use Maatwebsite\Excel\Facades\Excel;
use Cartalyst\Sentinel\Users\EloquentUser;
use Sentinel;
use Activation; 

use App\System\Eloquent\SysSetting;
use App\System\Eloquent\ResetPwdCode;
use Mail;
use Config;

class UserController extends Controller
{
    use ExcelTrait;

    public function __construct()
    {
        $this->middleware('privilege:sys_admin', [ 'except' => [ 'register', 'search', 'show', 'sendMailForResetpwd', 'showResetpwd', 'doResetpwd' ] ]);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $s = $request->input('s');
        $users = [];
        if ($s)
        {
            $search_users = EloquentUser::Where('first_name', 'like', '%' . $s .  '%')
                                ->orWhere('email', 'like', '%' . $s .  '%')
                                ->get([ 'first_name', 'last_name', 'email', 'invalid_flag' ]);

            $i = 0;
            foreach ($search_users as $key => $user)
            {
                if ((isset($user->invalid_flag) && $user->invalid_flag === 1) || Activation::completed($user) === false || $user->email === 'admin@action.view')
                {
                    continue;
                }

                $users[$i]['id'] = $user->id;
                $users[$i]['name'] = $user->first_name ?: '';
                $users[$i]['email'] = $user->email;
                if (++$i >= 10)
                {
                    break;
                }
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => $users ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = EloquentUser::where('email', '<>', '')->where('email', '<>', 'admin@action.view');

        if ($name = $request->input('name'))
        {
            $query->where(function ($query) use ($name) {
                $query->where('email', 'like', '%' . $name . '%')->orWhere('name', 'like', '%' . $name . '%');
            });
        }

        if ($group_id = $request->input('group'))
        {
            $group = Group::find($group_id);
            if ($group)
            {
                $query->whereIn('_id', $group->users ?: []);
            }
        }

        if ($directory = $request->input('directory'))
        {
            $query->where('directory', $directory);
        }

        // get total
        $total = $query->count();

        $query->orderBy('_id', 'asc');

        $page_size = 50;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);
        $all_users = $query->get([ 'first_name', 'last_name', 'email', 'phone', 'directory', 'invalid_flag' ]);

        $users = [];
        foreach ($all_users as $user)
        {
            $tmp = [];
            $tmp['id'] = $user->id;
            $tmp['first_name'] = $user->first_name;
            $tmp['email'] = $user->email;
            $tmp['phone'] = $user->phone ?: '';
            $tmp['groups'] = array_column(Group::whereRaw([ 'users' => $user->id ])->get([ 'name' ])->toArray() ?: [], 'name');
            $tmp['directory'] = $user->directory ?: 'self';
            $tmp['status'] = $user->invalid_flag === 1 ? 'invalid' : (Activation::completed($user) ? 'active' : 'unactivated');

            $users[] = $tmp;
        }
        return Response()->json([ 'ecode' => 0, 'data' => $users, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size, 'groups' => Group::all(), 'directories' => Directory::all() ] ]); 
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        if (!($first_name = $request->input('first_name')))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10100);
        }

        if (!($email = $request->input('email')))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10101);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('the email has already been registered.', -10102);
        }

        if (!$password = $request->input('password'))
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10103);
        }

        $user = Sentinel::register([ 'first_name' => $first_name, 'email' => $email, 'password' => $password ], true);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!($first_name = $request->input('first_name')))
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10100);
        }

        if (!($email = $request->input('email')))
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10101);
        }

        if (Sentinel::findByCredentials([ 'email' => $email ]))
        {
            throw new \InvalidArgumentException('email has already existed.', -10102);
        }

        $phone = $request->input('phone') ? $request->input('phone') : '';

        $user = Sentinel::register([ 'first_name' => $first_name, 'email' => $email, 'password' => 'actionview', 'phone' => $phone ], true);
        $user->status = Activation::completed($user) ? 'active' : 'unactivated';

        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * import the users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function imports(Request $request)
    {
        if (!($fid = $request->input('fid')))
        {
            throw new \UnexpectedValueException('the user file ID can not be empty.', -11140);
        }

        $pattern = $request->input('pattern');
        if (!isset($pattern))
        {
            $pattern = '1';
        }

        $file = config('filesystems.disks.local.root', '/tmp') . '/' . substr($fid, 0, 2) . '/' . $fid;
        if (!file_exists($file))
        {
            throw new \UnexpectedValueException('the file cannot be found.', -11141);
        }

        Excel::load($file, function($reader) use($pattern) {
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();

            $fields = [ 'first_name' => '姓名', 'email' => '邮箱', 'phone' => '手机号' ];
            $data = $this->arrangeExcel($data, $fields);

            foreach ($data as $value) 
            {
                if (!isset($value['first_name']) || !$value['first_name'])
                {
                    throw new \UnexpectedValueException('there is empty value in the name column', -10110);
                }

                if (!isset($value['email']) || !$value['email'])
                {
                    throw new \UnexpectedValueException('there is empty value in the email column', -10111);
                }
            }

            foreach ($data as $value)
            {
                $old_user = Sentinel::findByCredentials([ 'email' => $value['email'] ]);
                if ($old_user)
                {
                    if ($pattern == '1')
                    {
                        continue;
                    }
                    else
                    {
                        Sentinel::update($old_user, $value + [ 'password' => 'actionview' ]); 
                    }

                }
                else
                {
                    Sentinel::register($value + [ 'password' => 'actionview' ], true);
                }
            }
        });

        return Response()->json([ 'ecode' => 0, 'data' => [ 'ok' => true ] ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Response()->json([ 'ecode' => 0, 'data' => Sentinel::findById($id) ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $first_name = $request->input('first_name');
        if (isset($first_name))
        {
            if (!$first_name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10100);
            }
        }

        $email = $request->input('email');
        if (isset($email))
        {
            if (!$email)
            {
                throw new \UnexpectedValueException('the email can not be empty.', -10101);
            }
            if ($user = Sentinel::findByCredentials([ 'email' => $email ]))
            {
                if ($user->id !== $id) {
                    throw new \InvalidArgumentException('email has already existed.', -10102);
                }
            }
        }

        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10106);
        }
        if (isset($user->diectory) && $user->directory && $user->diectory != 'self')
        {
            throw new \UnexpectedValueException('the user come from external directroy.', -10109);
        }

        $valid = Sentinel::validForUpdate($user, array_only($request->all(), ['first_name', 'email', 'phone', 'invalid_flag']));
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10107);
        }

        $user = Sentinel::update($user, array_only($request->all(), ['first_name', 'email', 'phone', 'invalid_flag']));
        $user->status = $user->invalid_flag === 1 ? 'invalid' : (Activation::completed($user) ? 'active' : 'unactivated');

        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10106);
        }
        if (isset($user->diectory) && $user->directory && $user->diectory != 'self')
        {
            throw new \UnexpectedValueException('the user come from external directroy.', -10109);
        }

        $user->delete();
        Event::fire(new DelUserEvent($id));
        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => $id ] ]);
    }

    /**
     * delete all selected users.
     *
     * @return \Illuminate\Http\Response
     */
    public function delMultiUsers(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected users cannot been empty.', -10108);
        }

        $deleted_ids = [];
        foreach ($ids as $id)
        {
            $user = Sentinel::findById($id);
            if ($user)
            {
                if (isset($user->directory) && $user->directory && $user->directory != 'self')
                {
                    continue;
                }

                $user->delete();
                Event::fire(new DelUserEvent($id));
                $deleted_ids[] = $id;
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $deleted_ids ] ]);
    }

    /**
     * valid/invalid all selected users.
     *
     * @return \Illuminate\Http\Response
     */
    public function InvalidateMultiUsers(Request $request)
    {
        $ids = $request->input('ids');
        if (!isset($ids) || !$ids)
        {
            throw new \InvalidArgumentException('the selected users cannot been empty.', -10108);
        }

        $flag = $request->input('flag') ?: 1;

        $new_ids = [];
        foreach ($ids as $id)
        {
            $user = Sentinel::findById($id);
            if ($user)
            {
                if (isset($user->directory) && $user->directory && $user->directory != 'self')
                {
                    continue;
                }
                Sentinel::update($user, [ 'invalid_flag' => $flag ]);
                $new_ids[] = $id;
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => [ 'ids' => $new_ids ] ]);
    }

    /**
     * reset the user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function renewPwd(Request $request, $id)
    {
        $user = Sentinel::findById($id);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user does not exist.', -10106);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => 'actionview' ]);
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10107);
        }

        $user = Sentinel::update($user, [ 'password' => 'actionview' ]);
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * send the reset password link to the mail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendMailForResetpwd(Request $request)
    {
        $email = $request->input('email');
        if (!isset($email) || !$email)
        {
            throw new \UnexpectedValueException('the email can not be empty.', -10019);
        }

        $obscured_email = $sendto_email = $email;

        $last_reset_times = ResetPwdCode::where('requested_at', '>=', time() - 10 * 60)->count();
        if ($last_reset_times >= 10)
        {
            throw new \UnexpectedValueException('sending the email is too often.', -10016);
        }

        $last_reset_times = ResetPwdCode::where('requested_at', '>=', time() - 10 * 60)->where('email', $email)->count();
        if ($last_reset_times >= 3)
        {
            throw new \UnexpectedValueException('sending the email is too often.', -10016);
        }

        $user = Sentinel::findByCredentials([ 'email' => $email ]);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not exists.', -10010);
        }
        else if ($user->invalid_flag === 1)
        {
            throw new \UnexpectedValueException('the user has been disabled.', -10011);
        }
        else if ($user->directory && $user->directory != 'self')
        {
            throw new \UnexpectedValueException('the user is external sync user.', -10012);
        }

        if ($email === 'admin@action.view')
        {
            if (isset($user->bind_email) && $user->bind_email)
            {
                $sendto_email = $user->bind_email;
                $sections = explode('@', $user->bind_email);
                $sections[0] = substr($sections[0], 0, 1) . '***' . substr($sections[0], -1, 1);
                $obscured_email = implode('@', $sections);
            }
            else
            {
                throw new \UnexpectedValueException('the related email is not bound.', -10013);
            }
        }

        $data = [];
        $data['email'] = $email;
        $rand_code = md5($email . mt_rand() . microtime());
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $data['url'] = $http_type . $_SERVER['HTTP_HOST'] . '/resetpwd?code=' . $rand_code;

        $this->sendMail($sendto_email, $data);

        ResetPwdCode::create([
            'email' => $email,
            'code' => $rand_code,
            'requested_at' => time(),
            'expired_at' => time() + 24 * 60 * 60,
        ]);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'sendto_email' => $obscured_email ] ]);
    }

    /**
     * send the reset link to the address.
     *
     * @param  string $to
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    public function sendMail($to, $data)
    {
        $syssetting = SysSetting::first()->toArray();
        if (isset($syssetting['mailserver'])
            && isset($syssetting['mailserver']['send'])
            && isset($syssetting['mailserver']['smtp'])
            && isset($syssetting['mailserver']['send']['from'])
            && isset($syssetting['mailserver']['smtp']['host'])
            && isset($syssetting['mailserver']['smtp']['port'])
            && isset($syssetting['mailserver']['smtp']['username'])
            && isset($syssetting['mailserver']['smtp']['password']))
        {
            Config::set('mail.from', $syssetting['mailserver']['send']['from']);
            Config::set('mail.host', $syssetting['mailserver']['smtp']['host']);
            Config::set('mail.port', $syssetting['mailserver']['smtp']['port']);
            Config::set('mail.encryption', isset($syssetting['mailserver']['smtp']['encryption']) && $syssetting['mailserver']['smtp']['encryption'] ? $syssetting['mailserver']['smtp']['encryption'] : null);
            Config::set('mail.username', $syssetting['mailserver']['smtp']['username']);
            Config::set('mail.password', $syssetting['mailserver']['smtp']['password']);
        }
        else
        {
            throw new \UnexpectedValueException('the smtp server is not configured.', -10014);
        }

        $mail_prefix = 'ActionView';
        if (isset($syssetting['mailserver']['send']['prefix'])
            && $syssetting['mailserver']['send']['prefix'])
        {
            $mail_prefix = $syssetting['mailserver']['send']['prefix'];
        }

        $subject = '[' . $mail_prefix . ']重置密码';

        try {
            Mail::send('emails.resetpwdlink', $data, function($message) use($to, $subject) {
                $message->from(Config::get('mail.from'), 'master')
                    ->to($to)
                    ->subject($subject);
            });
        } catch (Exception $e){
            throw new Exception('send mail failed.', -15200);
        }
    }

    /**
     * show the reset password link.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function showResetpwd(Request $request)
    {
        $code = $request->input('code');
        if (!isset($code) || !$code)
        {
            throw new \UnexpectedValueException('the link is not exists.', -10018);
        }

        $reset_code = ResetPwdCode::where('code', $code)->first();
        if (!$reset_code)
        {
            throw new \UnexpectedValueException('the link is not exists.', -10018);
        }

        if ($reset_code->invalid_flag == 1)
        {
            throw new \UnexpectedValueException('the link has been invalid.', -10020);
        }
        else if ($reset_code->expired_at < time())
        {
            throw new \UnexpectedValueException('the link has been expired.', -10017);
        }

        $email = $reset_code->email;
        $user = Sentinel::findByCredentials([ 'email' => $email ]);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not exists.', -10010);
        }
        else if ($user->invalid_flag === 1)
        {
            throw new \UnexpectedValueException('the user has been disabled.', -10011);
        }
        else if ($user->directory && $user->directory != 'self')
        {
            throw new \UnexpectedValueException('the user is external sync user.', -10012);
        }

        return Response()->json([ 'ecode' => 0, 'data' => [ 'email' => $reset_code['email'] ] ]);
    }

    /**
     * reset the password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function doResetpwd(Request $request)
    {
        $code = $request->input('code');
        if (!isset($code) || !$code)
        {
            throw new \UnexpectedValueException('the link is not exists.', -10018);
        }

        $password = $request->input('password');
        if (!isset($password) || !$password)
        {
            throw new \UnexpectedValueException('the password can not be empty.', -10103);
        }

        $reset_code = ResetPwdCode::where('code', $code)->first();
        if (!$reset_code)
        {
            throw new \UnexpectedValueException('the link is not exists.', -10018);
        }

        if ($reset_code->invalid_flag == 1)
        {
            throw new \UnexpectedValueException('the link has been invalid.', -10020);
        }
        else if ($reset_code->expired_at < time())
        {
            throw new \UnexpectedValueException('the link has been expired.', -10017);
        }

        $email = $reset_code->email;
        $user = Sentinel::findByCredentials([ 'email' => $email ]);
        if (!$user)
        {
            throw new \UnexpectedValueException('the user is not exsits.', -10010);
        }
        else if ($user->invalid_flag === 1)
        {
            throw new \UnexpectedValueException('the user has been disabled.', -10011);
        }
        else if ($user->directory && $user->directory != 'self')
        {
            throw new \UnexpectedValueException('the user is external sync user.', -10012);
        }

        $valid = Sentinel::validForUpdate($user, [ 'password' => $password ]);
        if (!$valid)
        {
            throw new \UnexpectedValueException('updating the user does fails.', -10107);
        }

        $user = Sentinel::update($user, [ 'password' => $password ]);

        $reset_code->invalid_flag = 1;
        $reset_code->save();
        
        return Response()->json([ 'ecode' => 0, 'data' => $user ]);
    }

    /**
     * Download user template file.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function downloadUserTpl(Request $request)
    {
        $output = fopen('php://output', 'w') or die("can't open php://output");  

        header("Content-type:text/csv;charset=utf-8");
        header("Content-Disposition:attachment;filename=import-user-template.csv");

        fputcsv($output, [ 'name', 'email', 'phone' ]);  
        fputcsv($output, [ 'Tom', 'tom@actionview.cn', '13811111111' ]);  
        fputcsv($output, [ 'Alice', 'alice@actionview.cn', '13611111111' ]);  
        fclose($output) or die("can't close php://output"); 
        exit;
    }
}
