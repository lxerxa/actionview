<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class State extends Model
{
    //
    protected $table = 'config_state';

    protected $fillable = array(
        'name',
        'category',
        'description',
        'project_key',
        'sn'
    );
}
