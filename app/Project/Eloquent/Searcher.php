<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Searcher extends Model
{
    //
    protected $table = 'searcher';

    protected $fillable = array(
        'name',
        'query',
        'user',
        'project_key',
        'sn',
        'description'
    );
}
