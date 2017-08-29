<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\DelUserEvent;
use App\Acl\Eloquent\Roleactor;
use App\Acl\Eloquent\group;
use App\Project\Eloquent\UserProject;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserDelListener 
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
        $this->delUserFromRole($event->user_id);
        $this->delUserFromGroup($event->user_id);
        $this->delUserProject($event->user_id);
    }

    /**
     * del user from project role
     *
     * @param  string  $user_id
     * @return void
     */
    public function delUserFromRole($user_id)
    {
        $roleactors = Roleactor::whereRaw([ 'user_ids' => $user_id ])->get([ 'user_ids' ]);
        foreach ($roleactors as $roleactor)
        {
            $new_user_ids = [];
            $old_user_ids = $roleactor->user_ids ?: [];
            foreach ($old_user_ids as $uid)
            {
                if ($uid != $user_id)
                {
                    $new_user_ids[] = $uid;
                }
            }
            if ($new_user_ids)
            {
                $roleactor->user_ids = $new_user_ids;
                $roleactor->save();
            }
            else
            {
                $roleactor->delete();
            }
        }
    }

    /**
     * del user from group 
     *
     * @param  string  $user_id
     * @return void
     */
    public function delUserFromRole($user_id)
    {
        $groups = Group::whereRaw([ 'user_ids' => $user_id ])->get([ 'user_ids' ]);
        foreach ($groups as $group)
        {
           $new_user_ids = [];
           $old_user_ids = $group->user_ids ?: [];
           foreach ($old_user_ids as $uid)
           {
               if ($uid != $user_id)
               {
                   $new_user_ids[] = $uid;
               }
           }
           $group->user_ids = $new_user_ids;
           $group->save();
        }
    }

    /**
     * delete users from project
     *
     * @param  array  $user_id
     * @return void
     */
    public function delUserProject($user_id)
    {
        $links = UserProject::where('user_id', $user_id)->get([ 'user_id' ]);
        foreach ($links as $link)
        {
            $link->delete();
        }
    }
}
