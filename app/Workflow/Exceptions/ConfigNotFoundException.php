<?php

namespace App\Workflow\Exceptions;

use Exception;

class ConfigNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
