<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;
use App\Project\Provider;

class BoardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
    }

    public function getAccess($project_key) {

$example = [ 
  'id' => '111',
  'name' => '1111111111',
  'query' => [],
  'columns' => [
    [ 'name' => '待处理', 'states' => [ [ 'id' => 'Open', 'name' => '开始' ], [ 'id' => 'Reopen', 'name' => '重新开始' ] ] ],
    [ 'name' => '处理中', 'states' => [ [ 'id' => 'In Progess', 'name' => '处理中' ] ] ],
    [ 'name' => '关闭', 'states' => [ [ 'id' => 'Resolved', 'name' => '已完成' ], [ 'id' => 'Closed', 'name' => '关闭' ] ] ]
  ],
  'filters' => [
    [ 'id' => '11111', 'name' => '111111' ],
    [ 'id' => '22222', 'name' => '222222' ],
    [ 'id' => '33333', 'name' => '333333' ],
  ],
];

        return Response()->json([ 'ecode' => 0, 'data' => [ 'id' => '111' ], 'options' => [ 'kanbans' => [ $example ] ] ]);
    }

    public function setAccess($project_key) {
    }
}
