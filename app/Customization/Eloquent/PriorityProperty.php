<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class PriorityProperty extends Model
{
    //
    protected $table = 'config_priority_property';

    protected $fillable = array(
        'project_key',
        'sequence',
        'defaultValue'
    );
}
