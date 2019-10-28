<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\Webhooks;
use App\Project\Eloquent\Project;
use App\Project\Provider;
use DB;

class WebhooksController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $Webhooks = Webhooks::where('project_key', $project_key)->get();
        return Response()->json(['ecode' => 0, 'data' => $Webhooks]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $insValues = [];

        if (!($request_url = $request->input('request_url')))
        {
            throw new \UnexpectedValueException('the request url can not be empty.', -16010);
        }
        $insValues['request_url'] = $request_url;

        //if (!($events = $request->input('events')))
        //{
        //    throw new \UnexpectedValueException('the name can not be empty.', -10300);
        //}
        $insValues['events'] = $request->input('events') ?: [];

        if ($token = $request->input('token'))
        {
            $insValues['token'] = $token;
        }

        if ($ssl = $request->input('ssl'))
        {
            $insValues['ssl'] = $ssl;
        }

        $webhook = Webhooks::create([ 'project_key' => $project_key, 'status' => 'enabled' ] + $insValues);
        return Response()->json(['ecode' => 0, 'data' => $webhook]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        $webhook = Webhooks::find($id);
        if (!$webhook || $project_key != $webhook->project_key)
        {
            throw new \UnexpectedValueException('the webhook does not exist or is not in the project.', -16011);
        }

        $updValues = [];
        if ($status = $request->input('status'))
        {
            $updValues['status'] = $status;
        }

        if ($updValues)
        {
            $webhook->fill($updValues)->save();
        }

        return Response()->json(['ecode' => 0, 'data' => Webhooks::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $webhook = Webhooks::find($id);
        if (!$webhook || $project_key != $webhook->project_key)
        {
            throw new \UnexpectedValueException('the webhook does not exist or is not in the project.', -16011);
        }

        Webhooks::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
