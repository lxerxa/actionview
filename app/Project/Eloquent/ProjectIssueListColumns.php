<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ProjectIssueListColumns extends Model
{
    //
    protected $table = 'project_issuelist_columns';

    protected $fillable = array(
        'project_key',
        'column_keys',
        'columns'
    );
}
