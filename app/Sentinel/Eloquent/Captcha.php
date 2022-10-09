<?php

namespace App\Sentinel\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Captcha extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'captchas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'random',
        'created_at',
    ];
}
