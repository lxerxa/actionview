<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;

use App\Project\Eloquent\UserGroupProject;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRoleSetListener
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
     * @param  FieldChangeEvent  $event
     * @return void
     */
    public function handle(Event $event)
    {
        if ($event instanceof AddUserToRoleEvent) {
            $this->linkUserWithProject($event->user_ids, $event->project_key);
        } elseif ($event instanceof DelUserFromRoleEvent) {
            $this->unlinkUserWithProject($event->user_ids, $event->project_key);
        }
    }

    /**
     * add users to project
     *
     * @param  array  $user_ids
     * @param  string $project_key
     * @return void
     */
    public function linkUserWithProject($user_ids, $project_key)
    {
        foreach ($user_ids as $user_id) {
            $link = UserGroupProject::where('ug_id', $user_id)->where('project_key', $project_key)->first();
            if ($link) {
                $link->increment('link_count');
            } else {
                UserGroupProject::create([ 'ug_id' => $user_id, 'project_key' => $project_key, 'type' => 'user', 'link_count' => 1 ]);
            }
        }
    }

    /**
     * delete users from project
     *
     * @param  array  $user_ids
     * @param  string $project_key
     * @return void
     */
    public function unlinkUserWithProject($user_ids, $project_key)
    {
        foreach ($user_ids as $user_id) {
            $link = UserGroupProject::where('ug_id', $user_id)->where('project_key', $project_key)->first();
            if ($link) {
                $link->decrement('link_count');
            }
        }
    }
}
