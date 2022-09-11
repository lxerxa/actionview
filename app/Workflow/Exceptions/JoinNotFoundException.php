<?php

namespace App\Workflow\Exceptions;

use Exception;

class JoinNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
