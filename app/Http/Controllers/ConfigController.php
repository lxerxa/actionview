<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;
use App\Project\Provider;

class ConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $types = Provider::getTypeList($project_key); 
        foreach ($types as $type)
        {
            if (isset($type->disabled) && $type->disabled)
            {
                continue;
            }
            $type->screen = $type->screen;
            $type->workflow = $type->workflow;
        }

        $priorities = Provider::getPriorityList($project_key);
        $roles = Provider::getRoleList($project_key);

        return Response()->json([ 'ecode' => 0, 'data' => [ 'types' => $types, 'roles' => $roles, 'priorities' => $priorities ] ]);
    }
}
