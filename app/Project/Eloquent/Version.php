<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Version extends Model
{
    //
    protected $table = 'version';

    protected $fillable = array(
        'name',
        'project_key',
        'start_time',
        'end_time',
        'released_time',
        'status',
        'creator',
        'description'
    );
}
