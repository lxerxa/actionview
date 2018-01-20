<?php

namespace App\Acl\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class RolePermissions extends Model
{
    protected $table = 'acl_role_permissions';

    protected $fillable = array(
        'role_id',
        'project_key',
        'permissions'
    );
}
