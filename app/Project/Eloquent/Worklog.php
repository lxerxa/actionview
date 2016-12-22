<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Worklog extends Model
{
    //
    protected $table = 'worklog';

    protected $fillable = array(
        'project_key',
        'issue_id',
        'creator',
        'started_at',
        'spend',
        'adjust_type',
        'leave_estimate',
        'comments'
    );
}
