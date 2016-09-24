<?php

namespace App\Customization\Eloquent; 

use Jenssegers\Mongodb\Eloquent\Model;

class Type extends Model
{
    //
    protected $table = 'config_type';

    protected $fillable = array(
        'name',
        'screen_id',
        'workflow_id',
        'description',
        'project_key',
        'sn'
    );

    public function screen()
    {
        return $this->belongsTo('App\Customization\Eloquent\Screen');
    }

    public function workflow()
    {
        return $this->belongsTo('App\Workflow\Eloquent\Definition', 'workflow_id');
    }
}
