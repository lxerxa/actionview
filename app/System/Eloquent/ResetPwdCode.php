<?php

namespace App\System\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ResetPwdCode extends Model
{
    //
    protected $table = 'reset_pwd_code';

    protected $fillable = array(
        'email',
        'code',
        'requested_at',
        'expired_at',
        'invalid_flag'
    );
}
