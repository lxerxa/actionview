<?php

namespace App\ActiveDirectory\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Directory extends Model
{
    protected $table = 'active_directory';

    protected $fillable = array(
        'name',
        'type',
        'invalid_flag',
        'configs'
    );
}
