<?php

namespace App\Customization\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class EventNotifications extends Model
{
    //
    protected $table = 'config_event_notifications';

    protected $fillable = array(
        'project_key',
        'event_id',
        'notifications'
    );
}
