<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Watch extends Model
{
    //
    protected $table = 'watch';

    protected $fillable = array(
        'project_key',
        'issue_id',
        'user'
    );
}
