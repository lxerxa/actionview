<?php

namespace App\System\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class SysSetting extends Model
{
    protected $table = 'sys_setting';

    protected $fillable = array(
        'properties',
        'mailserver',
        'sysroles'
    );
}
