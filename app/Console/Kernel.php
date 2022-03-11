<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\SendEmails',
        'App\Console\Commands\SyncLdap',
        'App\Console\Commands\SnapSprint',
        'App\Console\Commands\RemoveLogs',
        'App\Console\Commands\RemoveMessages',
        // Commands\Inspire::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('email:send')
                 ->everyMinute()
                 ->withoutOverlapping();
        $schedule->command('sprint:snap')
                 ->dailyAt('23:20');
        $schedule->command('ldap:sync')
                 ->dailyAt('01:00');
        $schedule->command('logs:remove')
                 ->dailyAt('02:00');
        $schedule->command('messages:remove')
                 ->dailyAt('02:30');
    }
}
