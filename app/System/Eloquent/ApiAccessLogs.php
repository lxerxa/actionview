<?php
namespace App\System\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ApiAccessLogs extends Model
{
    //
    protected $table = 'api_access_logs';

    protected $fillable = array(
        'user',
        'project_key',
        'module',
        'requested_start_at',
        'requested_end_at',
        'exec_time',
        'request_source_ip',
        'request_url',
        'request_user_agent',
        'request_method',
        'request_body',
        'response_status'
    );
}
