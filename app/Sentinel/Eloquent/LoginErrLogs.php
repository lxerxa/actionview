<?php

namespace App\Sentinel\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class LoginErrLogs extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'login_error_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'mobile',
        'host',
        'created_at',
    ];
}
