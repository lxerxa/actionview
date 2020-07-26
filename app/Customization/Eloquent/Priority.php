<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Priority extends Model
{
    //
    protected $table = 'config_priority';

    protected $fillable = array(
        'name',
        'color',
        'description',
        'project_key',
        'sn'
    );
}
