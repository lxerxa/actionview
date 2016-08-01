<?php

namespace App\Project;

use Jenssegers\Mongodb\Eloquent\Model;

class Project extends Model
{
    //
    protected $table = 'project';

    protected $fillable = array(
        'name',
        'key',
        'creator',
        'type',
        'description'
    );
}
