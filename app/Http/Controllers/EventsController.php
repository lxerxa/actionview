<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Events;
use App\Customization\Eloquent\EventNotifications;
use App\Project\Provider;

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $events = Provider::getEventList($project_key)->toArray();
        foreach ($events as $key => $event)
        {
            $notifications = $this->getNotifications($project_key, $event['_id']);
            if ($notifications)
            {
                $events[$key]['notifications'] = $notifications;
            }
        }

	$roles = Provider::getRoleList($project_key, ['name']);
        $users = Provider::getUserList($project_key);
        return Response()->json(['ecode' => 0, 'data' => $events, 'options' => [ 'roles' => $roles, 'users' => $users ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name can not be empty.', -10002);
        }

        if (Provider::isEventExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('event name cannot be repeated', -10002);
        }

        $event = Events::create([ 'project_key' => $project_key ] + $request->all());
        return Response()->json(['ecode' => 0, 'data' => $event]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $event = Events::find($id);
        return Response()->json(['ecode' => 0, 'data' => $event]);
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
        $event = Events::find($id);
        if (!$event || $project_key != $event->project_key)
        {
            throw new \UnexpectedValueException('the event does not exist or is not in the project.', -10002);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
            if ($event->name !== $name && Provider::isEventExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('event name cannot be repeated', -10002);
            }
        }
        $event->fill($request->except(['project_key']))->save();

        $notifications = $request->input('notifications');
        if (isset($notifications))
        {
            $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $id ])->first();
            $en && $en->delete();

            EventNotifications::create([ 'project_key' => $project_key, 'event_id' => $id, 'notifications' => $notifications ]);
        }

        $event = Events::find($id);
        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $id ])->first();
        if ($en)
        {
            $event->notifications = $en->notifications;
        }

        return Response()->json(['ecode' => 0, 'data' => $event]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $event = Events::find($id);
        if (!$event || $project_key != $event->project_key)
        {
            throw new \UnexpectedValueException('the event does not exist or is not in the project.', -10002);
        }

        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $id ])->first();
        $en && $en->delete();

        Events::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * get users by array id.
     *
     * @param  string $project_key
     * @param  string $event_id
     * @return array 
     */
    public function getNotifications($project_key, $event_id)
    {
        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $event_id ])->first();
        return $en && isset($en->notifications) ? $en->notifications : [];
    }
}
