<?php
namespace App\Listeners;
use App\Events\Event;
use App\Events\IssueEvent;
use App\Events\VersionEvent;

use App\Project\Eloquent\WebhookEvents;
use App\Project\Eloquent\Webhooks;
use App\Project\Eloquent\Version;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use DB;

class WebhooksRequestListener 
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  FileChangeEvent  $event
     * @return void
     */
    public function handle(Event $event)
    {
        $event_key = $event->param['event_key'];
        $project_key = $event->project_key;

        $webhooks = Webhooks::where('project_key', $project_key)->where('status', 'enabled')->get();
        foreach ($webhooks as $webhook)
        {
            $events = isset($webhook->events) && $webhook->events ? $webhook->events : [];
            if (in_array($event_key, $events))
            {
                $this->push2WebhookEvents($event);
            }
        }
    }

    public function push2WebhookEvents(Event $event)
    {
        $event_key = $event->param['event_key'];
        $project_key = $event->project_key;
        $user = $event->user;

        if ($event instanceof IssueEvent)
        {
            if (!isset($event->param['issue_id']))
            {
                return;
            }

            $data = DB::collection('issue_' . $project_key)->where('_id', $event->param['issue_id'])->first();

            $data['project_key'] = $project_key;
            $data['event'] = $event_key;

            if ($event_key == 'add_worklog' || $event_key == 'edit_worklog')
            {
                $data['worklog'] = $event->param['data'];
            }

            WebhookEvents::create([
                'project_key' => $project_key,
                'user' => $user,
                'data' => $data,
                'flag' => 0
            ]);
        }
        else if ($event instanceof VersionEvent)
        {
            if (!isset($event->param['data']))
            {
                return;
            }

            $data = $event->param['data'];

            $data['event'] = $event_key;

            WebhookEvents::create([
                'project_key' => $project_key,
                'user' => $user,
                'data' => $data,
                'flag' => 0
            ]);
        }
    }
}
