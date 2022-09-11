<?php

namespace App\Workflow\Exceptions;

use Exception;

class StateNotActivatedException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
