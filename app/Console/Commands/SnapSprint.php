<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\SprintDayLog;
use App\Project\Eloquent\Worklog;

use DB;
use Exception;

class SnapSprint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sprint:snap';

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
        $active_sprints = Sprint::where('status', 'active')->where('start_time', '<', time())->where('complete_time', '>', time());
        foreach ($active_sprints as $sprint)
        {
            $project_key = $sprint->project_key;

            $issue_state_map = [];
            $issues = isset($sprint->issues) ? $sprint->issues : [];
            foreach ($issues as $issue_no)
            {
                $issue = DB::collection('issue_' . $project_key)->where('no', $issue_no)->first();
                if ($issue && isset($issue['state'])) 
                {
                    $issue_state_map[$issue_no] = $issue['state'];
                }
            }

            SprintDayLog::create([ 'project_key' => $project_key, 'no' => $sprint->no, 'day' => date('Y/m/d'), 'issue_state_map' => $issue_state_map ]);
        }
    }
}
