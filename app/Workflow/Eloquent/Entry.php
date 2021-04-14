<?php

namespace App\Workflow\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Entry extends Model
{
    protected $table = 'oswf_entry';

    protected $fillable = array(
        'definition_id', 
        'creator',
        'state',
        'propertysets' 
    );

    public function definition()
    {
        return $this->belongsTo('App\Workflow\Eloquent\Definition');
    }

    public function currentSteps()
    {
        return $this->hasMany('App\Workflow\Eloquent\CurrentStep');
    }
}

