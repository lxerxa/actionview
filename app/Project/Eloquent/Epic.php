<?php

namespace App\Project\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Epic extends Model
{
    //
    protected $table = 'epic';

    protected $fillable = array(
        'name',
        'bgColor',
        'description',
        'project_key',
        'sn'
    );
}
