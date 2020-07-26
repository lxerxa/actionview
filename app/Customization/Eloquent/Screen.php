<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Screen extends Model
{
    //
    protected $table = 'config_screen';

    protected $fillable = array(
        'name',
        'field_ids',
        'schema',
        'description',
        'project_key'
    );
}
