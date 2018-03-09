<?php

namespace App\Project\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Epic extends Model
{
    //
    protected $table = 'epic';

    protected $fillable = array(
        'name',
        'bgcolor',
        'description',
        'project_key',
        'sn'
    );
}
