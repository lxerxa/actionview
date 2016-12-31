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
        'recorder',
        'recorded_at',
        'started_at',
        'spend',
        'adjust_type',
        'leave_estimate',
        'cut',
        'comments',
        'edit_flag'
    );
}
