<?php

namespace App\Acl\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Roleactor extends Model
{
    protected $table = 'acl_roleactor';

    protected $fillable = array(
        'role_id',
        'project_key',
        'user_ids',
        'group_ids'
    );
}
