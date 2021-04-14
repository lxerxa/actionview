<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class GroupProject extends Model
{
    //
    protected $table = 'group_project';

    protected $fillable = array(
        'group_id',
        'project_key',
        'link_count'
    );
}
