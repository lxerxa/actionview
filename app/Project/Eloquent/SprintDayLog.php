<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class SprintDayLog extends Model
{
    //
    protected $table = 'sprint_log';

    protected $fillable = array(
        'project_key',
        'no',
        'day',
        'issue_state_map',
        'issue_leave_estimate_map',
    );
}
