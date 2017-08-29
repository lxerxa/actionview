<?php

namespace App\Acl\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Group extends Model
{
    protected $table = 'acl_group';

    protected $fillable = array(
        'name',
        'users',
        'description'
    );
}
