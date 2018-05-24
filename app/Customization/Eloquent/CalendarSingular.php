<?php

namespace App\Customization\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class CalendarSingular extends Model
{
    //
    protected $table = 'config_calendar_singular';

    protected $fillable = array(
        'day',
        'flag'
    );
}
