<?php

namespace App\Workflow\Exceptions;

use Exception;

class ResultNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
