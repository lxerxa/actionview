<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class UserIssueListColumns extends Model
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
