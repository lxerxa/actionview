<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Customization\Eloquent\Events;
use App\Customization\Eloquent\EventNotifications;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\Module;
use App\Project\Eloquent\Watch;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Eloquent\Group;

use Cartalyst\Sentinel\Users\EloquentUser;

use DB;
use Mail;
use Exception;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * get event map from key to id.
     *
     * @return array
     */
    public function getEventMap()
    {
        $map = [];
        $events = Events::whereRaw([ 'key' => [ '$exists' => 1 ] ])->get();
        foreach ($events as $event)
        {
            $key = $event->key;
            $map[$key] = $event->id; 
        }
        return $map;
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
        if (!$en)
        {
            $en = EventNotifications::where([ 'project_key' => '$_sys_$', 'event_id' => $event_id ])->first();
        }
        return $en && isset($en->notifications) ? $en->notifications : [];
    }

    /**
     * get users by role id.
     *
     * @param  string $project_key
     * @param  string $role_id
     * @return array 
     */
    public function getUsersByRoleId($project_key, $role_id)
    {
        $actor = Roleactor::where([ 'project_key' => $project_key, 'role_id' => $role_id ])->first();
        if (!$actor) { return [ 'users' => [], 'groups' => [] ]; }

        $user_ids = isset($actor->user_ids) && $actor->user_ids ? $actor->user_ids : []; 

        if (isset($actor->group_ids) && $actor->group_ids)
        {
            $groups = Group::find($actor->group_ids);
            foreach ($groups as $group)
            {
                $user_ids = array_merge($user_ids, isset($group->users) ? $group->users : []);
            }
        }

        return $user_ids;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $event_map = $this->getEventMap();

        $data = DB::collection('mq')->orderBy('_id', 'asc')->get();
        foreach ($data as $val)
        {
            $uids = [];
            $project_key = $val['project_key'];

            $project = Project::where('key', $project_key)->first();
            if (!$project) { continue; }

            $activity = DB::collection('activity_' . $project_key)->where('_id', $val['activity_id'])->first();

            $issue = DB::collection('issue_' . $project_key)->where('_id', $activity['issue_id'])->first();
            if (!$issue) { continue; }

            $event_id = isset($event_map[$activity['event_key']]) && $event_map[$activity['event_key']] ? $event_map[$activity['event_key']] : $activity['event_key'];
            $notifications = $this->getNotifications($project_key, $event_id);

            foreach ($notifications as $notification)
            {
                if ('current_user' === $notification)
                {
                    $uids[] = $activity['user']['id'];
                }
                else if ('reporter' === $notification)
                {
                    $uids[] = $issue['reporter']['id'];
                }
                else if ('assignee' === $notification)
                {
                    $uids[] = $issue['assignee']['id'];
                }
                else if ('watchers' === $notification)
                {
                    $watchers = Watch::where('issue_id', $issue['_id']->__toString())->get();
                    foreach ($watchers as $watcher)
                    {
                        $uids[] = $watcher['user']['id'];
                    }
                }
                else if ('project_principal' === $notification)
                {
                    $uids[] = $project->principal['id'];
                }
                else if ('module_principal' === $notification && isset($issue['module']))
                {
                    $module = Module::find($issue['module']);
                    if ($module)
                    {
                        $uids[] = $module->principal['id'];
                    }
                }
                else if (is_array($notification) && isset($notification['key']))
                {
                    if ($notification['key'] === 'user' && isset($notification['value']) && isset($notification['value']['id']))
                    {
                        $uids[] = $notification['value']['id'];
                    }
                    else if ($notification['key'] === 'role' && isset($notification['value']))
                    {
                        $role_users = $this->getUsersByRoleId($project_key, $notification['value']);
                        $uids = array_merge($uids, $role_users);
                    }
                }
            }

            $atWho = [];
            if ($activity['event_key'] == 'add_comments' or $activity['event_key'] == 'edit_comments' or $activity['event_key'] == 'del_comments')
            {
                if (isset($activity['data']) && isset($activity['data']['atWho']) && $activity['data']['atWho'])
                {
                    foreach ($activity['data']['atWho'] as $who)
                    {
                        $uids[] = $who['id'];
                        $atWho[] = $who['id'];
                    }
                }
            }

            $data = [ 
              'project' => $project,
              'issue' => $issue,
              'event_key' => $activity['event_key'], 
              'user' => $activity['user'], 
              'data' => isset($activity['data']) ? $activity['data'] : [], 
              'domain' => env('DOMAIN', 'www.actionview.cn'),
            ];

            $to_users = EloquentUser::find(array_unique($uids)); 
            foreach ($to_users as $to_user)
            {
                $new_data = $data;
                if (in_array($to_user->id, $atWho)) 
                {
                    $new_data['at'] = true;
                }

                $from = $activity['user']['name']; 
                $to = $to_user['email'];
                $subject = '[ActionView](' . $project['key'] . '-' . $issue['no'] . ')' . (isset($issue['title']) ? $issue['title'] : '-');

                try {
                    Mail::send('emails.issue', $new_data, function($message) use($from, $to, $subject) {
                      $message->from(env('MAIL_ADDRESS', 'actionview@126.com'), $from)
                          ->to($to)
                          ->subject($subject);
                    });
                } catch (Exception $e){
                }
            }
            DB::collection('mq')->where('_id', $val['_id']->__toString())->delete();
        }
    }
}
