<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Field extends Model
{
    //
    protected $table = 'config_field';

    protected $fillable = array(
        'name',
        'key',
        'type',
        'applyToTypes',
        'description',
        'defaultValue',
        'optionValues',
        'minValue',
        'maxValue',
        'maxLength',
        'project_key'
    );
}
