<?php

namespace App\Workflow;

class Util
{
    //
    public function preStepFunc1($args)
    {
        echo 'pre step func1 ===';
    }

    //
    public function postStepFunc1($args)
    {
        echo 'post step func1 ===';
    }

    //
    public function preStepFunc2($args)
    {
        echo 'pre step func2 ===';
    }

    //
    public function postStepFunc2($args)
    {
        echo 'post step func2 ===';
    }

    //
    public function preActionFunc1($args)
    {
        echo 'pre action func1 ===';
    }

    //
    public function postActionFunc1($args)
    {
        echo 'post action func1 ===';
    }

    //
    public function preActionFunc2($args)
    {
        echo 'pre action func2 ===';
    }

    //
    public function postActionFunc2($args)
    {
        echo 'post action func2 ===';
    }

    //
    public function preResultFunc1($args)
    {
        echo 'pre result func1 ===';
    }

    //
    public function postResultFunc1($args)
    {
        echo 'post result func1 ===';
    }

    //
    public function preResultFunc2($args)
    {
        echo 'pre result func2 ===';
    }

    //
    public function postResultFunc2($args)
    {
        echo 'post result func2 ===';
    }

    //
    public function trueCondition1($args)
    {
        return true;
    }

    //
    public function trueCondition2($args)
    {
        return true;
    }

    //
    public function falseCondition1($args)
    {
        return false;
    }

    //
    public function falseCondition2($args)
    {
        return false;
    }
}
