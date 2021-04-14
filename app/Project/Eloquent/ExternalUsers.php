<?php
namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class ExternalUsers extends Model
{
    //
    protected $table = 'external_users';

    protected $fillable = array(
        'project_key',
        'user',
        'status',
        'pwd'
    );

    protected $hidden = [
        'pwd',
    ];
}
