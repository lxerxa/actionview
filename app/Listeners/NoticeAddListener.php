<?php
namespace App\Listeners;

use App\Events\Event;
use App\Events\FileUploadEvent;
use App\Events\FileDelEvent;
use App\Events\IssueEvent;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use DB;

class NoticeAddListener
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
     * @param  FileChangeEvent  $event
     * @return void
     */
    public function handle(Event $event)
    {
        // this activity_id is used for notice

        if ($event instanceof FileUploadEvent) {
            $this->putMQ($event->project_key, $event->issue_id, $event->user, [ 'event_key' => 'add_file',  'data' => $event->file_id ]);
        } elseif ($event instanceof FileDelEvent) {
            $this->putMQ($event->project_key, $event->issue_id, $event->user, [ 'event_key' => 'add_file',  'data' => $event->file_id ]);
        } elseif ($event instanceof IssueEvent) {
            $this->putMQ($event->project_key, $event->issue_id, $event->user, $event->param);
        }
    }

    /**
     * add notice queue.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @param  string $activity_id
     * @return void
     */
    public function putMQ($project_key, $issue_id, $user, $param)
    {
        $info = [ 'project_key' => $project_key, 'issue_id' => $issue_id, 'event_key' => $param['event_key'], 'user' => $user, 'data' => isset($param['data']) ? $param['data'] : '', 'created_at' => time() ];

        DB::collection('mq')->insert($info);
    }
}
