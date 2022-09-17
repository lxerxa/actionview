<?php
namespace App\Listeners;

use App\Events\Event;
use App\Events\IssueEvent;
use App\Events\VersionEvent;

use App\Customization\Eloquent\State;
use App\Customization\Eloquent\Resolution;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\Type;

use App\Project\Provider;
use App\Project\Eloquent\WebhookEvents;
use App\Project\Eloquent\Webhooks;
use App\Project\Eloquent\Version;
use App\Project\Eloquent\Project;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use DB;
use App\Utils\CurlRequest;

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
            if (in_array('edit_issue', $events))
            {
                $events = array_merge($events, ['assign_issue', 'move_issue', 'reset_issue']);
            }

            if (in_array($event_key, $events) && $webhook->request_url)
            {
                @$this->push2WebhookEvents($event, $webhook->request_url, $webhook->token ?: '');
            }
        }
    }

    public function push2WebhookEvents(Event $event, $request_url, $token='')
    {
        $event_key = $event->param['event_key'];
        $project_key = $event->project_key;
        $user = $event->user;

        if ($event instanceof IssueEvent)
        {
            if (!isset($event->issue_id))
            {
                return;
            }

            $data = [];
            if ($event_key == 'add_worklog' || $event_key == 'edit_worklog')
            {
                $data['worklog'] = array_only($event->param['data'], [ 'spend', 'started_at', 'adjust_type', 'comments' ]);
                if (isset($data['worklog']['started_at'])) 
                {
                    $data['worklog']['started_at'] = date('Y-m-d H:i', $data['worklog']['started_at']); 
                }
            }

            $issue = DB::collection('issue_' . $project_key)->where('_id', $event->issue_id)->first();
            if (!$issue)
            {
            	return;
            }

            $schema = Provider::getSchemaByType($issue['type']);
            if (!$schema)
            {
            	return;
            }

            $new_issue = [];

            $type = Type::find($issue['type']);
            if (!$type)
            {
            	return;
            }
            $new_issue['type'] = $type->name;

            foreach ($schema as $key => $field) 
            {
            	$field_key = $field['key'];
            	if (!isset($issue[$field_key]))
            	{
	            continue;
            	}

            	$field_value = $issue[$field_key];
            	if (!$field_value || in_array($field['type'], [ 'SingleUser', 'MultiUser' ]))
            	{
                    $new_issue[$field_key] = $field_value;
            	    continue;
            	}

                if (isset($field['optionValues']) && $field['optionValues'])
            	{
                    $opv = [];
                    
                    if (!is_array($field_value))
                    {
                        $fieldValues = explode(',', $field_value);
                    }
                    else
                    {
                        $fieldValues = $field_value;
                    }

                    foreach ($field['optionValues'] as $ov)
                    {
                        if (in_array($ov['id'], $fieldValues))
                        {
                            $opv[] = $ov['name'];
                        }
                    }

                    $new_issue[$field_key] = count($opv) == 1 && !is_array($field_value) && strpos($field_value, ',') === false ? $opv[0] : $opv;
            	}
            	else if ($field['type'] == 'DatePicker' || $field['type'] == 'DateTimePicker')
            	{
                    if ($field_value)
            	    {
                        $new_issue[$field_key] = date($field['type'] == 'DatePicker' ? 'Y-m-d' : 'Y-m-d H:i:s', $field_value);
            	    }
            	    else
            	    {
            	        $new_issue[$field_key] = $field_value;
            	    }
            	}
            	else
            	{
            	    $new_issue[$field_key] = $field_value;
            	}
            }

            if (isset($issue['state']) && !isset($new_issue['state']))
            {
            	$state = State::where('_id', $issue['state'])->orWhere('key', $issue['state'])->first();
            	if ($state)
            	{
                    $new_issue['state'] = $state->name;
            	}
            }

            if (isset($issue['resolution']) && !isset($new_issue['resolution']))
            {
            	$resolution = Resolution::where('_id', $issue['resolution'])->orWhere('key', $issue['resolution'])->first();
            	if ($resolution)
            	{
                    $new_issue['resolution'] = $resolution->name;
            	}
            }

            if (isset($issue['priority']) && !isset($new_issue['priority']))
            {
            	$priority = Priority::where('_id', $issue['priority'])->orWhere('key', $issue['priority'])->first();
            	if ($priority)
            	{
                    $new_issue['priority'] = $priority->name;
            	}
            }

            if (isset($issue['descriptions']))
            {
            	$new_issue['descriptions'] = $issue['descriptions'];
            }

            if (isset($issue['labels']))
            {
            	$new_issue['labels'] = $issue['labels'];
            }

            if (isset($issue['assignee']))
            {
            	$new_issue['assignee'] = $issue['assignee'];
            }

            if (isset($issue['progress']))
            {
            	$new_issue['progress'] = $issue['progress'];
            }

            if (isset($issue['expect_start_time']))
            {
            	$new_issue['expect_start_time'] = $issue['expect_start_time'] ? date('Y-m-d', $issue['expect_start_time']) : '';
            }

            if (isset($issue['expect_complete_time']))
            {
            	$new_issue['expect_complete_time'] = $issue['expect_complete_time'] ? date('Y-m-d', $issue['expect_complete_time']) : '';
            }

            if (isset($issue['parent_id']))
            {
                $parent = DB::collection('issue_' . $project_key)->where('_id', $issue['parent_id'])->first();
                if ($parent)
                {
                    $new_issue['parent'] = array_only($parent, [ 'no', 'title' ]);
                }
            }

            $data['issue'] = $new_issue;
            $data['event'] = $event_key;

            $project = Project::where('key', $project_key)->first();
            if ($project)
            {
                $data['project'] = array_only($project->toArray(), [ 'key', 'name' ]);
            }

            $data['user'] = $user;

            $header = [ 'Content-Type: application/json', 'Expect:', 'X-Actionview-Token: ' . ($token ?: '') ];
            CurlRequest::post($request_url, $header, $data, 1);
        }
        else if ($event instanceof VersionEvent)
        {
            if (!isset($event->param['data']))
            {
                return;
            }

            $data['version'] = $event->param['data'];
            if (isset($data['version']['start_time']))
            {
                $data['version']['start_time'] = date('Y-m-d', $data['version']['start_time']);
            }
            if (isset($data['version']['end_time']))
            {
                $data['version']['end_time'] = date('Y-m-d', $data['version']['end_time']);
            }
            if (isset($data['version']['released_time']))
            {
                $data['version']['released_time'] = date('Y-m-d H:i:s', $data['version']['released_time']);
            }

            $data['event'] = $event_key;

            $project = Project::where('key', $project_key)->first();
            if ($project)
            {
                $data['project'] = array_only($project->toArray(), [ 'key', 'name' ]);
            }

            $data['user'] = $user;

            $header = [ 'Content-Type: application/json', 'Expect:', 'X-Actionview-Token: ' . ($token ?: '') ];
            CurlRequest::post($request_url, $header, $data, 1);
        }
    }
}
