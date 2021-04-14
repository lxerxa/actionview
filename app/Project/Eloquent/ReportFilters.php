<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ReportFilters extends Model
{
    //
    protected $table = 'report_filters';

    protected $fillable = array(
        'project_key',
        'mode',
        'user',
        'filters'
    );
}
