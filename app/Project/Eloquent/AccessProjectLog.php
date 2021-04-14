<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class AccessProjectLog extends Model
{
    //
    protected $table = 'access_project_log';

    protected $fillable = array(
        'user_id',
        'project_key',
        'latest_access_time'
    );
}
