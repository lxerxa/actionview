<?php

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
       'App\Events\FieldChangeEvent' => [
            'App\Listeners\FieldConfigChangeListener',
        ],
       'App\Events\FieldDeleteEvent' => [
            'App\Listeners\FieldConfigChangeListener',
        ],
       'App\Events\ResolutionConfigChangeEvent' => [
            'App\Listeners\PropertyConfigChangeListener',
        ],
       'App\Events\PriorityConfigChangeEvent' => [
            'App\Listeners\PropertyConfigChangeListener',
        ],
       'App\Events\AddUserToRoleEvent' => [
            'App\Listeners\UserRoleSetListener',
        ],
       'App\Events\DelUserFromRoleEvent' => [
            'App\Listeners\UserRoleSetListener',
        ],
       'App\Events\DelUserEvent' => [
            'App\Listeners\UserDelListener',
        ],
       'App\Events\FileUploadEvent' => [
            'App\Listeners\FileChangeListener',
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\FileDelEvent' => [
            'App\Listeners\FileChangeListener',
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\IssueCreateEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\IssueEditEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\IssueDelEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\CommentsAddEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\CommentsEditEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\CommentsDelEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\WorklogAddEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\WorklogEditEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
       'App\Events\WorklogDelEvent' => [
            'App\Listeners\ActivityAddListener',
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
