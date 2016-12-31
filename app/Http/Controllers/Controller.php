<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use Sentinel;

use MongoDB\BSON\ObjectID; 

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        $this->user = Sentinel::getUser(); 
    }

    public function arrange($data)
    {
        if (!is_array($data))
        {
            return $data;
        }

        if (array_key_exists('_id', $data))
        {
            $data['_id'] = $data['_id'] instanceof ObjectID ? $data['_id']->__toString() : $data['_id'];
        }

        foreach ($data as $k => $val)
        {
            $data[$k] = $this->arrange($val);
        }

        return $data;
    }

    /**
     * check the timetracking.
     *
     * @return bool
     */
    public function ttCheck($ttString)
    {
        $ttString = strtolower(trim($ttString));
        $ttValues = explode(' ', $ttString);
        foreach ($ttValues as $ttValue)
        {
            if (!$ttValue)
            {
                continue;
            }

            $lastChr = substr($ttValue, -1);
            if ($lastChr !== 'w' && $lastChr !== 'd' && $lastChr !== 'h' && $lastChr !== 'm')
            {
                return false;
            }

            $ttNum = substr($ttValue, 0, -1);
            if ($ttNum && !is_numeric($ttNum))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * handle the timetracking.
     *
     * @return string
     */
    public function ttHandle($ttString)
    {
        if (!$ttString)
        {
            return '';
        }

        $W2M = 5 * 8 * 60;
        $D2M = 8 * 60;
        $H2M = 60;

        $tt_in_min = 0;

        $ttString = strtolower(trim($ttString));
        $ttValues = explode(' ', $ttString);
        foreach ($ttValues as $ttValue)
        {
            if (!$ttValue)
            {
                continue;
            }

            $lastChr = substr($ttValue, -1);
            $ttNum   = substr($ttValue, 0, -1) === '' ? 1 : substr($ttValue, 0, -1);

            if ($lastChr == 'w')
            {
                $tt_in_min += $ttNum * $W2M;
            }
            else if ($lastChr == 'd')
            {
                $tt_in_min += $ttNum * $D2M;
            }
            else if ($lastChr == 'h')
            {
                $tt_in_min += $ttNum * $H2M;
            }
            else if ($lastChr == 'm')
            {
                $tt_in_min += $ttNum;
            }
        }

        $newTT = [];
        $new_remain_min = ceil($tt_in_min);
        if ($new_remain_min >= 0)
        {
            $new_weeknum = floor($tt_in_min / $W2M);
            if ($new_weeknum > 0)
            {
                $newTT[] = $new_weeknum . 'w';
            }
        }

        $new_remain_min = $tt_in_min % $W2M;
        if ($new_remain_min >= 0)
        {
            $new_daynum = floor($new_remain_min / $D2M);
            if ($new_daynum > 0)
            {
                $newTT[] = $new_daynum . 'd';
            }
        }

        $new_remain_min = $new_remain_min % $D2M;
        if ($new_remain_min >= 0)
        {
            $new_hournum = floor($new_remain_min / $H2M);
            if ($new_hournum > 0)
            {
                $newTT[] = $new_hournum . 'h';
            }
        }

        $new_remain_min = $new_remain_min % $H2M;
        if ($new_remain_min > 0)
        {
            $newTT[] = $new_remain_min . 'm';
        }

        if (!$newTT)
        {
            $newTT[] = '0m';
        }

        return implode(' ', $newTT);
    }
}
