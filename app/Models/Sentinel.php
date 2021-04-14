<?php

namespace App\Models;

use Cartalyst\Sentinel\Laravel\Facades\Sentinel as CartalystSentinel;

class Sentinel extends CartalystSentinel
{
    public function getAuthIdentifier()
    {
        return $this->getUserId();
    }
}
