<?php
namespace App\Listeners;

use App\Events\Event;
use App\Events\FileUploadEvent;
use App\Events\FileDelEvent;
use App\Project\Provider;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use DB;

class FileChangeListener 
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
        if ($event instanceof FileUploadEvent)
        {
            $this->updIssueField($event->project_key, $event->issue_id, $event->field_key, $event->file_id, $event->user, 1);
        }
        else if ($event instanceof FileDelEvent)
        {
            $this->updIssueField($event->project_key, $event->issue_id, $event->field_key, $event->file_id, $event->user, 2);
        }
    }
    /**
     * update the issue file field.
     *
     * @param  string  $project_key
     * @param  string  $issue_id
     * @param  string  $field_key
     * @param  string  $file_id
     * @param  int flag
     * @return void
     */
    public function updIssueField($project_key, $issue_id, $field_key, $file_id, $user, $flag)
    {
        $table = 'issue_' . $project_key;
        $issue = DB::collection($table)->where('_id', $issue_id)->first();

        if (!isset($issue[$field_key]) || !is_array($issue[$field_key]))
        {
            $issue[$field_key] = [];
        }
        if ($flag == 1)
        {
            array_push($issue[$field_key], $file_id);
        } 
        else 
        {
            $index = array_search($file_id, $issue[$field_key]);
            if ($index !== false)
            {
                array_splice($issue[$field_key], $index, 1);
            }
            else
            {
                return;
            }
        }

        $issue['updated_at'] = time();
        // update issue file field
        DB::collection($table)->where('_id', $issue_id)->update([ $field_key => $issue[$field_key], 'updated_at' => time(), 'modifier' => $user ]);

        // add to histroy table
        Provider::snap2His($project_key, $issue_id, '', [ $field_key ]);
    }
}
