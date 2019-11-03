<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class File extends Model
{
    //
    protected $table = 'file';

    protected $fillable = array(
        'name',
        'type',
        'size',
        'uploader',
        'index',
        'thumbnails_index',
        'del_flg'
    );
}
