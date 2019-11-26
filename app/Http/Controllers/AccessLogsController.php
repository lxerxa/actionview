<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\System\Eloquent\ApiAccessLogs;

class AccessLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ApiAccessLogs::query();

        $uid = $request->input('uid');
        if (isset($uid) && $uid)
        {
            $query = $query->where('user.id', $uid);
        }

        $method = $request->input('method');
        if (isset($method) && $method)
        {
            $query = $query->where('request_method', $method);
        }

        $project_key = $request->input('project_key');
        if (isset($project_key) && $project_key)
        {
            $query = $query->where('project_key', $project_key);
        }

        $module = $request->input('module');
        if (isset($module) && $module)
        {
            $query = $query->where('module', $module);
        }

        $request_time = $request->input('request_time');
        if (isset($request_time) && $request_time)
        {
            if (strpos($request_time, '~') !== false)
            {
                $sections = explode('~', $request_time);
                if ($sections[0])
                {
                    $query->where('requested_start_at', '>=', strtotime($sections[0]) * 1000);
                }
                if ($sections[1])
                {
                    $query->where('requested_end_at', '<=', strtotime($sections[1] . ' 23:59:59') * 1000);
                }
            }
        }

        $url = $request->input('request_url');
        if (isset($url) && $url)
        {
            $query->where('request_url', 'like', '%' . $url . '%');
        }

        $request_source_ip = $request->input('request_source_ip');
        if (isset($request_source_ip) && $request_source_ip)
        {
            $query->where('request_source_ip', $request_source_ip);
        }

        $exec_time = $request->input('exec_time');
        if (isset($exec_time) && $exec_time)
        {
            $flag = substr($exec_time, 0, 1);
            if ($flag == '-')
            {
                $query->where('exec_time', '<=', abs(floatval($exec_time)) * 1000);
            }
            else
            {
                $query->where('exec_time', '>=', abs(floatval($exec_time)) * 1000);
            }
        }

        // get total
        $total = $query->count();
        $query->orderBy('_id', 'desc');
        $page_size = 100;
        $page = $request->input('page') ?: 1;
        $query = $query->skip($page_size * ($page - 1))->take($page_size);

        $logs = $query->get();

        return Response()->json([ 'ecode' => 0, 'data' => $logs, 'options' => [ 'total' => $total, 'sizePerPage' => $page_size ] ]);
    }
}
