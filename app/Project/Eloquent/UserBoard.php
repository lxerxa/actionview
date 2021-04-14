<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserBoard extends Model
{
    //
    protected $table = 'user_board';

    protected $fillable = array(
        'user_id',
        'board_id'
    );
}
