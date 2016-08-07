<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\DelUserEvent;
use App\Acl\Eloquent\Role;
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
        $roles = Role::whereRaw([ 'user_ids' => $user_id ])->get([ 'user_ids' ]);
        foreach ($roles as $role)
        {
           $new_user_ids = [];
           $old_user_ids = $role->user_ids ?: [];
           foreach ($old_user_ids as $uid)
           {
               if ($uid != $user_id)
               {
                   $new_user_ids[] = $uid;
               }
           }
           $role->user_ids = $new_user_ids;
           $role->save();
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
