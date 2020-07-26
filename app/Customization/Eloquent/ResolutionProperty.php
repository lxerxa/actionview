<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ResolutionProperty extends Model
{
    //
    protected $table = 'config_resolution_property';

    protected $fillable = array(
        'project_key',
        'sequence',
        'defaultValue'
    );
}
