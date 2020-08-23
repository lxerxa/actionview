<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class DocumentFavorites extends Model
{
    //
    protected $table = 'document_favorites';

    protected $fillable = array(
        'project_key',
        'did',
        'user'
    );
}
