<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use DB;
use Exception;

class RemoveMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:remove';

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
        DB::collection('message')->where('created_at', '<', strtotime('-3 months'))->delete();
    }
}
