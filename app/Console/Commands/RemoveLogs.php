<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use DB;
use Exception;

use App\System\Eloquent\SysSetting;
use App\System\Eloquent\ApiAccessLogs;

class RemoveLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $durations = [ 
            '3m' => '3 months', 
            '6m' => '6 months', 
            '1y' => '1 year', 
            '2y' => '2 years', 
        ];

        $log_save_duration = '6 months';

        $syssetting = SysSetting::first()->toArray();
        if (isset($syssetting['properties']) && isset($syssetting['properties']['logs_save_duration']))
        {
            if (isset($durations[$syssetting['properties']['logs_save_duration']]))
            {
                $log_save_duration = $durations[$syssetting['properties']['logs_save_duration']];
            }
        }

        $removed_at = strtotime('-' . $log_save_duration);

        ApiAccessLogs::where('requested_start_at', '<', $removed_at)->delete();
    }
}
