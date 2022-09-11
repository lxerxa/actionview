<?php

namespace App\Workflow\Exceptions;

use Exception;

class EntryNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
