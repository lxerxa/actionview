<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\AddUserToRoleEvent;
use App\Events\DelUserFromRoleEvent;

use App\Project\UserProject;
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
    }

    /**
     * del user from project role
     *
     * @param  string  $user_id
     * @return void
     */
    public function delUserFromRole($user_id)
    {
    }

    /**
     * delete users from project
     *
     * @param  array  $user_id
     * @return void
     */
    public function delUserProject($user_id)
    {
    }
}
