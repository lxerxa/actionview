<?php

namespace App\Workflow\Exceptions;

use Exception;

class ActionNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
