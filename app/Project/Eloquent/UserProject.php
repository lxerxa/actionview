<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserProject extends Model
{
    //
    protected $table = 'user_project';

    protected $fillable = array(
        'user_id',
        'project_key',
        'link_count',
        'latest_access_time'
    );
}
