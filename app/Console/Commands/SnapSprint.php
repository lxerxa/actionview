<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Project\Eloquent\Sprint;
use App\Project\Eloquent\SprintDayLog;

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
        $active_sprints = Sprint::where('status', 'active')
            ->where('start_time', '<', time())
            ->where('complete_time', '>', time())
            ->get();

        foreach ($active_sprints as $sprint) {
            $project_key = $sprint->project_key;

            $contents = [];
            $issue_nos = isset($sprint->issues) ? $sprint->issues : [];
            $issues = DB::collection('issue_' . $project_key)
                ->where([ 'no' => [ '$in' => $issue_nos ] ])
                ->get();
            foreach ($issues as $issue) {
                $tmp = [];
                $tmp['no'] = $issue['no'];
                $tmp['state'] = isset($issue['state']) ? $issue['state'] : '';
                $tmp['story_points'] = isset($issue['story_points']) ? $issue['story_points'] : 0;
                $contents[] = $tmp;
            }

            SprintDayLog::where([ 'project_key' => $project_key, 'no' => $sprint->no, 'day' => date('Y/m/d') ])->delete();

            SprintDayLog::create([
                'project_key' => $project_key,
                'no' => $sprint->no,
                'day' => date('Y/m/d'),
                'issues' => $contents ]);
        }
    }
}
