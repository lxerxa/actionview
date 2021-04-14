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
        'App\Console\Commands\ImportFile',
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
                 ->everyMinute();
        $schedule->command('sprint:snap')
                 ->dailyAt('23:20');
        $schedule->command('ldap:sync')
                 ->daily();
        $schedule->command('logs:remove')
                 ->daily();
    }
}
