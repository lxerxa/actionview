<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\AddGroupToRoleEvent;
use App\Events\DelGroupFromRoleEvent;

use App\Project\Eloquent\UserGroupProject;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class GroupRoleSetListener 
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
        if ($event instanceof AddGroupToRoleEvent)
        {
            $this->linkGroupWithProject($event->group_ids, $event->project_key);
        }
        else if ($event instanceof DelGroupFromRoleEvent)
        {
            $this->unlinkGroupWithProject($event->group_ids, $event->project_key);
        }
    }

    /**
     * add users to project
     *
     * @param  array  $group_ids
     * @param  string $project_key
     * @return void
     */
    public function linkGroupWithProject($group_ids, $project_key)
    {
        foreach ($group_ids as $group_id)
        {
            $link = UserGroupProject::where('ug_id', $group_id)->where('project_key', $project_key)->first();
            if ($link)
            {
                $link->increment('link_count');
            }
            else
            {
                UserGroupProject::create([ 'ug_id' => $group_id, 'project_key' => $project_key, 'type' => 'group', 'link_count' => 1 ]);
            }
        }
    }

    /**
     * delete users from project
     *
     * @param  array  $group_ids
     * @param  string $project_key
     * @return void
     */
    public function unlinkGroupWithProject($group_ids, $project_key)
    {
        foreach ($group_ids as $group_id)
        {
            $link = UserGroupProject::where('ug_id', $group_id)->where('project_key', $project_key)->first();
            if ($link)
            {
                $link->decrement('link_count');
            }
        }
    }
}
