<?php

namespace App\Workflow\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Definition extends Model
{
    protected $table = 'oswf_definition';

    protected $fillable = array(
        'name',
        'screens',
        'project_key',
        'contents'
    );

    /**
     * Saves the workflow.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = array())
    {
        $this->validate();
        return parent::save();
    }

    /**
     * validate the workflow definition.
     *
     * @return bool
     * @throws \App\Workflow\Exception\NameRequiredException
     * @throws \App\Workflow\Exception\ConfigErrorException
     */
    public function validate()
    {
        return true;
    }

}
