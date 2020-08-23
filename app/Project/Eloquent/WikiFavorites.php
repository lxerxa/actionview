<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class WikiFavorites extends Model
{
    //
    protected $table = 'wiki_favorites';

    protected $fillable = array(
        'project_key',
        'wid',
        'user'
    );
}
