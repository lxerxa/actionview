<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserGroupProject extends Model
{
    //
    protected $table = 'user_group_project';

    protected $fillable = array(
        'ug_id',
        'project_key',
        'type',
        'link_count'
    );
}
