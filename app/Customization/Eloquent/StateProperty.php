<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class StateProperty extends Model
{
    //
    protected $table = 'config_state_property';

    protected $fillable = array(
        'project_key',
        'sequence'
    );
}
