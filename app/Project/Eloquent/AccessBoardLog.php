<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class AccessBoardLog extends Model
{
    //
    protected $table = 'access_board_log';

    protected $fillable = array(
        'user_id',
        'project_key',
        'board_id',
        'latest_access_time'
    );
}
