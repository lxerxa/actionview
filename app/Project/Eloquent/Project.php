<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Project extends Model
{
    //
    protected $table = 'project';

    protected $fillable = array(
        'name',
        'key',
        'principal',
        'category',
        'description',
        'creator',
        'status'
    );
}
