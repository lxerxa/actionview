<?php

namespace App\Acl\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserDirectory extends Model
{
    protected $table = 'user_directory';

    protected $fillable = array(
        'name',
        'type',
        'configs'
    );
}
