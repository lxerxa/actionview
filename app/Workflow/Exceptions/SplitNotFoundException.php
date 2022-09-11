<?php

namespace App\Workflow\Exceptions;

use Exception;

class SplitNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';
}
