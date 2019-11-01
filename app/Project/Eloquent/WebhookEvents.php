<?php
namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class WebhookEvents extends Model
{
    //
    protected $table = 'webhook_events';

    protected $fillable = array(
        'user_id',
        'project_key',
        'request_url',
        'token',
        'data',
        'flag'
    );
}
