<?php

namespace App\System\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user_setting';

    protected $fillable = array(
        'user_id',
        'notifications',
        'favorites'
    );
}
