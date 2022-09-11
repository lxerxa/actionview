<?php

namespace App\Workflow\Exceptions;

use Exception;

class StepNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
