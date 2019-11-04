<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Customization\Eloquent\Type;
use App\Customization\Eloquent\Priority;
use App\Customization\Eloquent\Events;
use App\Customization\Eloquent\EventNotifications;
use App\Project\Eloquent\Project;
use App\Project\Eloquent\Version;
use App\Project\Eloquent\Module;
use App\Project\Eloquent\Watch;
use App\Acl\Acl;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Eloquent\Group;

use App\Project\Eloquent\Sprint;

use Cartalyst\Sentinel\Users\EloquentUser;

use App\System\Eloquent\SysSetting;

use DB;
use Mail;
use Config;
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
     * The send mail prefix.
     *
     * @var string
     */
    protected $mail_prefix = 'ActionView';

    /**
     * The http-host configed.
     *
     * @var string
     */
    protected $http_host = 'localhost';

    /**
     * The mail server configed flag.
     *
     * @var bool
     */
    protected $is_configed = false;

    /**
     * The issue event map.
     *
     * @var array
     */
    protected $event_map = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $syssetting = SysSetting::first()->toArray();
        if (isset($syssetting['mailserver'])
            && isset($syssetting['mailserver']['send'])
            && isset($syssetting['mailserver']['smtp'])
            && isset($syssetting['mailserver']['send']['from'])
            && isset($syssetting['mailserver']['smtp']['host'])
            && isset($syssetting['mailserver']['smtp']['port'])
            && isset($syssetting['mailserver']['smtp']['username'])
            && isset($syssetting['mailserver']['smtp']['password']))
        {
            Config::set('mail.from', $syssetting['mailserver']['send']['from']);
            Config::set('mail.host', $syssetting['mailserver']['smtp']['host']);
            Config::set('mail.port', $syssetting['mailserver']['smtp']['port']);
            Config::set('mail.encryption', isset($syssetting['mailserver']['smtp']['encryption']) && $syssetting['mailserver']['smtp']['encryption'] ? $syssetting['mailserver']['smtp']['encryption'] : null);
            Config::set('mail.username', $syssetting['mailserver']['smtp']['username']);
            Config::set('mail.password', $syssetting['mailserver']['smtp']['password']);

            $this->is_configed = true;
        }

        if (isset($syssetting['mailserver'])
            && isset($syssetting['mailserver']['send'])
            && isset($syssetting['mailserver']['send']['prefix'])
            && $syssetting['mailserver']['send']['prefix'])
        {
            $this->mail_prefix = $syssetting['mailserver']['send']['prefix'];
        }

        if (isset($syssetting['properties']) && isset($syssetting['properties']['http_host']))
        {
            $this->http_host = $syssetting['properties']['http_host'];
        }

        $this->event_map = $this->getEventMap();
    }

    /**
     * whether the mail server is configed
     *
     * @return bool
     */
    public function isSmtpConfiged()
    {
        return $this->is_configed;
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
     * notice function for other actions.
     *
     * @param  array $project
     * @param  array $activity
     * @return void
     */
    public function otherNoticeHandle($project, $activity)
    {
        $data = [];
        $subject = '';
        $template = '';

        if ($activity['event_key'] == 'start_sprint')
        {
            $sprint_no = isset($activity['data']) && isset($activity['data']['sprint_no']) ? $activity['data']['sprint_no'] : '';
            if (!$sprint_no)
            {
                return;
            }

            $sprint = Sprint::where('project_key', $project->key)
                ->where('no', $sprint_no)
                ->first();
            if ($sprint->status !== 'active' || !isset($sprint['issues']))
            {
                return;
            }

            $issues = DB::collection('issue_' . $project->key)
                ->whereIn('no', $sprint['issues'])
                ->where('del_flg', '<>', 1)
                ->get();

            $data = [
                'project' => $project,
                'issues' => $issues,
                'sprint' => $sprint,
                'user' => $activity['user'],
                'http_host' => $this->http_host,
            ];

            $subject = '[' . $this->mail_prefix . '] ' . $project->key . ': Sprint-' . $sprint_no . ' 启动';
            $template = 'emails.sprint_start';
        }
        else if ($activity['event_key'] == 'complete_sprint')
        {
            $sprint_no = isset($activity['data']) && isset($activity['data']['sprint_no']) ? $activity['data']['sprint_no'] : '';
            if (!$sprint_no)
            {
                return;
            }

            $sprint = Sprint::where('project_key', $project->key)
                ->where('no', $sprint_no)
                ->first();

            if ($sprint->status !== 'completed')
            {
                return;
            }

            $completed_issues = [];
            $incompleted_issues = [];
            if (isset($sprint->completed_issues) && $sprint->completed_issues)
            {
                $completed_issues = DB::collection('issue_' . $project->key)
                    ->whereIn('no', $sprint->completed_issues)
                    ->where('del_flg', '<>', 1)
                    ->get();
            }

            if (isset($sprint->incompleted_issues) && $sprint->incompleted_issues)
            {
                $incompleted_issues = DB::collection('issue_' . $project->key)
                    ->whereIn('no', $sprint->incompleted_issues)
                    ->where('del_flg', '<>', 1)
                    ->get();
            }

            $data = [
                'project' => $project,
                'completed_issues' => $completed_issues,
                'incompleted_issues' => $incompleted_issues,
                'sprint' => $sprint,
                'user' => $activity['user'],
                'http_host' => $this->http_host,
            ];

            $subject = '[' . $this->mail_prefix . '] ' . $project->key . ': Sprint-' . $sprint_no . ' 完成';

            $template = 'emails.sprint_complete';
        }
        else if ($activity['event_key'] == 'release_version')
        {
            $release_version = isset($activity['data']) ? $activity['data'] : [];
            if (!$release_version)
            {
                return;
            }

            $released_issues = DB::collection('issue_' . $project->key)
                ->where('resolve_version', $release_version['_id'])
                ->where('resolution', '<>', 'Unresolved')
                ->where('del_flg', '<>', 1)
                ->get();

            $data = [
                'project' => $project,
                'released_issues' => $released_issues,
                'resolve_version' => [ 'id' => $release_version['_id'], 'name' => $release_version['name'] ],
                'user' => $activity['user'],
                'http_host' => $this->http_host,
            ];

            $subject = '[' . $this->mail_prefix . '] ' . $project->key . ':  版本-' . $release_version['name'] . ' 发布';

            $template = 'emails.version_release';
        }
        else if ($activity['event_key'] == 'create_release_version')
        {
            $released_issueids = isset($activity['data']['released_issues']) ? $activity['data']['released_issues'] : [];
            $release_version = isset($activity['data']['release_version']) ? $activity['data']['release_version'] : [];
            if (!$released_issueids || !$release_version)
            {
                return;
            }

            $released_issues = DB::collection('issue_' . $project->key)
                ->whereIn('_id', $released_issueids)
                ->where('del_flg', '<>', 1)
                ->get();

            $data = [
                'project' => $project,
                'released_issues' => $released_issues,
                'resolve_version' => [ 'id' => $release_version['_id'], 'name' => $release_version['name'] ],
                'user' => $activity['user'],
                'http_host' => $this->http_host,
            ];

            $subject = '[' . $this->mail_prefix . '] ' . $project->key . ':  版本-' . $release_version['name'] . ' 发布';

            $template = 'emails.version_release';
        }
        else if ($activity['event_key'] == 'create_wiki' || $activity['event_key'] == 'edit_wiki')
        {
            $wiki_id = isset($activity['data']['wiki_id']) ? $activity['data']['wiki_id'] : '';
            if (!$wiki_id)
            {
                return;
            }

            $wiki = DB::collection('wiki_' . $project->key)
                ->where('_id', $wiki_id)
                ->first();
            if (!$wiki) { return; }

            $data = [
                'project' => $project,
                'wiki' => [ 'id' => $wiki['_id']->__toString(), 'name' => $wiki['name'], 'parent'  => $wiki['parent'] == '0' ? 'root' : $wiki['parent'] ],
                'event_key' => $activity['event_key'],
                'user' => $activity['user'],
                'http_host' => $this->http_host,
            ];

            $subject = '[' . $this->mail_prefix . '] ' . $project->key . ': Wiki-' . $wiki['name'];

            $template = 'emails.wiki';
        }
        else
        {
            return;
        }

        $uids = Acl::getUserIdsByPermission('view_project', $project->key);

        $to_users = EloquentUser::find(array_unique($uids));

        foreach ($to_users as $to_user)
        {
            $from = $activity['user']['name'];
            $to = $to_user['email'];
            try {
                Mail::send($template, $data, function($message) use($from, $to, $subject) {
                    $message->from(Config::get('mail.from'), $from)
                        ->to($to)
                        ->subject($subject);
                });
            } catch (Exception $e) {
                continue;
            }
        }
    }

    /**
     * notice function for issue.
     *
     * @param  array $project
     * @param  array $activity
     * @return void
     */
    public function issueNoticeHandle($project, $activity)
    {
        $issue_id = $activity['issue_id'];

        $uids = [];
        $event_map = $this->event_map;

        $issue = DB::collection('issue_' . $project->key)->where('_id', $issue_id)->first();
        if (!$issue) { return; }

        $event_key = ($activity['event_key'] == 'add_file' || $activity['event_key'] == 'del_file') ? 'edit_issue' : $activity['event_key'];
        $event_id = isset($event_map[$event_key]) && $event_map[$event_key] ? $event_map[$event_key] : $event_key;

        $notifications = $this->getNotifications($project->key, $event_id);

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
                if (isset($issue['assignee']) && isset($issue['assignee']['id']))
                {
                    $uids[] = $issue['assignee']['id'];
                }
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
                if ($module && isset($module->principal) && isset($module->principal['id']))
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
                    $role_users = $this->getUsersByRoleId($project->key, $notification['value']);
                    $uids = array_merge($uids, $role_users);
                }
                else if ($notification['key'] === 'single_user_field' && isset($notification['value']))
                {
                    $key = $notification['value'];
                    if (isset($issue[$key]) && isset($issue[$key]['id']))
                    {
                        $uids[] = $issue[$key]['id'];
                    }
                }
                else if ($notification['key'] === 'multi_user_field' && isset($notification['value']))
                {
                    $key = $notification['value'];
                    if (isset($issue[$key]) && $issue[$key])
                    {
                        foreach ($issue[$key] as $v)
                        {
                            $uids[] = $v['id'];
                        }
                    }
                }
            }
        }

        if ($event_key == 'create_issue')
        {
            if (isset($issue['type']) && $issue['type'])
            {
                $type = Type::find($issue['type']);
                $issue['type'] = $type ? $type->name : '-';
            }
            if (isset($issue['priority']) && $issue['priority'])
            {
                $priority = Priority::where('_id', $issue['priority'])->orWhere('key', $issue['priority'])->first();
                $issue['priority'] = $priority ? $priority->name : '-';
            }
            else
            {
                $issue['priority'] = '-';
            }
        }

        $atWho = [];
        if ($event_key == 'add_comments' or $event_key == 'edit_comments' or $event_key == 'del_comments')
        {
            if (isset($activity['data']) && isset($activity['data']['atWho']) && $activity['data']['atWho'])
            {
                foreach ($activity['data']['atWho'] as $who)
                {
                    $uids[] = $who['id'];
                    $atWho[] = $who['id'];
                }
            }
            if (isset($activity['data']) && isset($activity['data']['to']) && $activity['data']['to'])
            {
                 $uids[] = $activity['data']['to']['id'];
            }
        }

        $data = [
            'project' => $project,
            'issue' => $issue,
            'event_key' => $activity['event_key'],
            'user' => $activity['user'],
            'data' => isset($activity['data']) ? $activity['data'] : [],
            'http_host' => $this->http_host,
        ];

        $to_users = EloquentUser::find(array_values(array_unique(array_filter($uids))));
        foreach ($to_users as $to_user)
        {
            $new_data = $data;
            if (in_array($to_user->id, $atWho))
            {
                $new_data['at'] = true;
            }

            $from = $activity['user']['name'];
            $to = $to_user['email'];
            $subject = '[' . $this->mail_prefix . '](' . $project->key . '-' . $issue['no'] . ')' . (isset($issue['title']) ? $issue['title'] : '-');
            try {
                Mail::send('emails.issue', $new_data, function($message) use($from, $to, $subject) {
                    $message->from(Config::get('mail.from'), $from)
                        ->to($to)
                        ->subject($subject);
                });
            } catch (Exception $e) {
                continue;
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $point = time();
        // clean up garbage message
        DB::collection('mq')->where('flag', '>', 0)->where('flag', '<', $point - 24 * 60 * 60)->delete();
        // mark up the notice message
        DB::collection('mq')->where('flag', 0)->update([ 'flag' => $point ]);

        if (!$this->isSmtpConfiged())
        {
            return;
        }

        $data = DB::collection('mq')->where('flag', $point)->orderBy('_id', 'asc')->get();
        foreach ($data as $val)
        {
            $uids = [];
            $project_key = $val['project_key'];

            $project = Project::where('key', $project_key)->first();
            if (!$project) { continue; }

            $activity = DB::collection('activity_' . $project_key)->where('_id', $val['activity_id'])->first();

            if (isset($activity['issue_id']) && $activity['issue_id'])
            {
                $this->issueNoticeHandle($project, $activity);
            }
            else
            {
                $this->otherNoticeHandle($project, $activity);
            }

            DB::collection('mq')->where('_id', $val['_id']->__toString())->delete();
        }
    }
}
