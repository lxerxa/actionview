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
        $events = Provider::getEventList($project_key);
        foreach ($events as $key => $event)
        {
            $event->notifications = $this->getNotifications($project_key, $event['_id']);
        }

	$roles = Provider::getRoleList($project_key, ['name']);
        $users = Provider::getUserList($project_key);

        $single_user_fields = [];
        $multi_user_fields = [];
        $fields = Provider::getFieldList($project_key);
        foreach ($fields as $field)
        {
            if ($field->type === 'SingleUser')
            {
                $single_user_fields[] = [ 'id' => $field->key, 'name' => $field->name ];
            } 
            else if ($field->type === 'MultiUser')
            {
                $multi_user_fields[] = [ 'id' => $field->key, 'name' => $field->name ];
            } 
        }

        return Response()->json(['ecode' => 0, 'data' => $events, 'options' => [ 'roles' => $roles, 'users' => $users, 'single_user_fields' => $single_user_fields, 'multi_user_fields' => $multi_user_fields ]]);
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
        if (!$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -12800);
        }

        if (Provider::isEventExisted($project_key, $name))
        {
            throw new \UnexpectedValueException('event name cannot be repeated', -12801);
        }

        $event = Events::create([ 'project_key' => $project_key, 'apply' => 'workflow' ] + $request->all());
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
            throw new \UnexpectedValueException('the event does not exist or is not in the project.', -12802);
        }

        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name)
            {
                throw new \UnexpectedValueException('the name can not be empty.', -12800);
            }
            if ($event->name !== $name && Provider::isEventExisted($project_key, $name))
            {
                throw new \UnexpectedValueException('event name cannot be repeated', -12801);
            }
        }
        $event->fill($request->except(['project_key']))->save();

        $event = Events::find($id);
        $event->notifications = $this->getNotifications($project_key, $id);

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
            throw new \UnexpectedValueException('the event does not exist or is not in the project.', -12802);
        }

        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $id ])->first();
        $en && $en->delete();

        Events::destroy($id);
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }

    /**
     * set notifications
     *
     * @param  string $project_key
     * @param  string $event_id
     * @return array
     */
    public function setNotify(Request $request, $project_key, $id)
    {
        $event = Events::find($id);
        if (!$event || ($event->project_key != '$_sys_$' && $project_key != $event->project_key))
        {
            throw new \UnexpectedValueException('the event does not exist or is not in the project.', -12802);
        }

        $notifications = $request->input('notifications');
        if (isset($notifications))
        {
            $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $id ])->first();
            $en && $en->delete();

            EventNotifications::create([ 'project_key' => $project_key, 'event_id' => $id, 'notifications' => $notifications ]);
        }
        $event->notifications = $this->getNotifications($project_key, $id);

        return Response()->json(['ecode' => 0, 'data' => $event]);
    }

    /**
     * get notifications by project_key and eventid. 
     *
     * @param  string $project_key
     * @param  string $event_id
     * @return array 
     */
    public function getNotifications($project_key, $event_id)
    {
        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $event_id ])->first();
        if (!$en && $project_key !== '$_sys_$')
        {
            $en = EventNotifications::where([ 'project_key' => '$_sys_$', 'event_id' => $event_id ])->first();
        }
        return $en && isset($en->notifications) ? $en->notifications : [];
    }

    /**
     * reset the notification.
     *
     * @param  string  $project_key
     * @param  string  $event_id
     * @return \Illuminate\Http\Response
     */
    public function reset($project_key, $event_id)
    {
        $en = EventNotifications::where([ 'project_key' => $project_key, 'event_id' => $event_id ])->first();
        $en && $en->delete();

        $event = Events::find($event_id)->toArray();

        $en = EventNotifications::where([ 'project_key' => '$_sys_$', 'event_id' => $event_id ])->first();
        $event['notifications'] = $en && isset($en->notifications) ? $en->notifications : [];

        return Response()->json(['ecode' => 0, 'data' => $event]);
    }
}
