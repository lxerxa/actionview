<?php

namespace App\Workflow\Exceptions;

use Exception;

class ResultNotAvailableException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
