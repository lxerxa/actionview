<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Customization\Eloquent\CalendarSingular; 

use DB;
use Exception;

class ImportCalendarSingular extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar-singular:import';

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
        $file = fopen(base_path() . "/calendar_singular.txt", "r");
        while(!feof($file))
        {
            $data = fgets($file);
            $tmp = explode(',', $data);
            if (count($tmp) !== 2)
            {
                continue;
            }

            $cs = CalendarSingular::where([ 'day' => trim($tmp[0]) ])->first();
            $cs && $cs->delete();

            CalendarSingular::create([ 'day' => trim($tmp[0]), 'flag' => intval(trim($tmp[1])) ]); 
        }
    }
}
