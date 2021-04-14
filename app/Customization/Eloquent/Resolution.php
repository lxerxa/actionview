<?php

namespace App\Customization\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Resolution extends Model
{
    //
    protected $table = 'config_resolution';

    protected $fillable = array(
        'name',
        'description',
        'project_key',
        'sn'
    );
}
