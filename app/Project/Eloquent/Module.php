<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Module extends Model
{
    //
    protected $table = 'module';

    protected $fillable = array(
        'name',
        'project_key',
        'principal',
        'defaultAssignee',
        'creator',
        'description',
        'sn'
    );
}
