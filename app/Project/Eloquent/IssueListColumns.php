<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class IssueListColumns extends Model
{
    //
    protected $table = 'user_issuelist_columns';

    protected $fillable = array(
        'project_key',
        'user',
        'column_keys',
        'columns'
    );
}
