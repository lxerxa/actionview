<?php

namespace App\Customization\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Events extends Model
{
    //
    protected $table = 'config_events';

    protected $fillable = array(
        'project_key',
        'name',
        'apply',
        'description'
    );
}
