<?php

namespace App\Workflow\Exceptions;

use Exception;

class FunctionNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
