<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Linked extends Model
{
    //
    protected $table = 'linked';

    protected $fillable = array(
        'src',
        'relation',
        'dest',
        'creator'
    );
}
