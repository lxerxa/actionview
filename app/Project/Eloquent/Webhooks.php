<?php
namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Webhooks extends Model
{
    //
    protected $table = 'webhooks';

    protected $fillable = array(
        'project_key',
        'request_url',
        'token',
        'events',
        'ssl',
        'status',
        'creator',
    );

    protected $hidden = [
        'token',
    ];
}
