<?php

namespace App\Project\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Labels extends Model
{
    //
    protected $table = 'labels';

    protected $fillable = array(
        'name',
        'project_key'
    );
}
