<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\DelUserEvent;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Eloquent\group;
use App\Project\Eloquent\UserGroupProject;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class GroupDelListener 
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
        $this->delGroupFromRole($event->group_id);
        $this->delGroupProject($event->group_id);
    }

    /**
     * del user from project role
     *
     * @param  string  $group_id
     * @return void
     */
    public function delGroupFromRole($group_id)
    {
        $roleactors = Roleactor::whereRaw([ 'group_ids' => $group_id ])->get([ 'group_ids' ]);
        foreach ($roleactors as $roleactor)
        {
            $new_group_ids = [];
            $old_group_ids = $roleactor->group_ids ?: [];
            foreach ($old_group_ids as $gid)
            {
                if ($gid != $group_id)
                {
                    $new_group_ids[] = $gid;
                }
            }
            if ($new_group_ids)
            {
                $roleactor->group_ids = $new_group_ids;
                $roleactor->save();
            }
            else
            {
                $roleactor->delete();
            }
        }
    }

    /**
     * delete users from project
     *
     * @param  array  $group_id
     * @return void
     */
    public function delGroupProject($group_id)
    {
        $links = UserGroupProject::where('ug_id', $group_id)->get();
        foreach ($links as $link)
        {
            $link->delete();
        }
    }
}
