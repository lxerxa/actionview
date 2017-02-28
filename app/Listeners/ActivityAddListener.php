<?php
namespace App\Listeners;

use App\Events\Event;
use App\Events\FileUploadEvent;
use App\Events\FileDelEvent;
use App\Events\IssueCreateEvent;
use App\Events\IssueEditEvent;
use App\Events\IssueDelEvent;
use App\Events\CommentsAddEvent;
use App\Events\CommentsEditEvent;
use App\Events\CommentsDelEvent;
use App\Events\WorklogAddEvent;
use App\Events\WorklogEditEvent;
use App\Events\WorklogDelEvent;
use App\Project\Provider;

use App\Project\Eloquent\File;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use DB;

class ActivityAddListener 
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
        $activity_id = '';

        if ($event instanceof FileUploadEvent)
        {
            $activity_id = $this->addFileActivity($event->project_key, $event->issue_id, 'add_file', $event->user, $event->file_id);
        }
        else if ($event instanceof FileDelEvent)
        {
            $activity_id = $this->addFileActivity($event->project_key, $event->issue_id, 'del_file', $event->user, $event->file_id);
        }
        else if ($event instanceof IssueEvent)
        {
            $activity_id = $this->addIssueActivity($event->project_key, $event->issue_id, $event->event_key, $event->user, $event->param);
        }

        if ($activity_id)
        {
            $this->putMQ($event->project_key, $event->issue_id, $activity_id);
        }
    }

    /**
     * add file activities.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @param  string $file_id
     * @param  object $user
     * @param  string $key
     * @return void
     */
    public function addFileActivity($project_key, $issue_id, $event_key, $user, $file_id)
    {
        $file_info = File::find($file_id);
        if (!$file_info)
        {
            return;
        }

        // insert activity into db.
        $info = [ 'issue_id' => $issue_id, 'operation' => $operation, 'user' => $user, 'summary' => $file_info->name, 'created_at' => time() ];
        return DB::collection('activity_' . $project_key)->insertGetId($info);
    }

    /**
     * add issue activities.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @param  string $event_key
     * @param  object $user
     * @param  string $param
     * @return void
     */
    public function addIssueActivity($project_key, $issue_id, $event_key, $user, $param='')
    {
        $info = [];

        if ($event_key === 'edit_issue')
        {
            $diff_items = []; $diff_keys = [];

            $snaps = DB::collection('issue_his_' . $project_key)->where('issue_id', $issue_id)->orderBy('operated_at', 'desc')->get();
            foreach ($snaps as $i => $snap)
            {
                if ($snap['_id']->__toString() != $param)
                {
                    continue;
                }

                $after_data = $snap['data'];
                $before_data = $snaps[$i + 1]['data'];
                foreach ($after_data as $key => $val)
                {
                    if (!isset($before_data[$key]) || $val != $before_data[$key])
                    {
                        $tmp = [];
                        $tmp['field'] = isset($val['name']) ? $val['name'] : '';
                        $tmp['after_value'] = isset($val['value']) ? $val['value'] : '';
                        $tmp['before_value'] = isset($before_data[$key]) && isset($before_data[$key]['value']) ? $before_data[$key]['value'] : '';

                        if (is_array($tmp['after_value']) && is_array($tmp['before_value']))
                        {
                            $diff1 = array_diff($tmp['after_value'], $tmp['before_value']);
                            $tmp['after_value'] = implode(',', $diff1);
                        }
                        else
                        {
                            if (is_array($tmp['after_value']))
                            {
                                $tmp['after_value'] = implode(',', $tmp['after_value']);
                            }
                        }
                        $diff_items[] = $tmp;
                        $diff_keys[] = $key;
                    }
                }

                foreach ($before_data as $key => $val)
                {
                    if (array_search($key, $diff_keys) !== false)
                    {
                        continue;
                    }
                    if (!isset($after_data[$key]) || $val != $after_data[$key])
                    {
                        $tmp = [];
                        $tmp['field'] = isset($val['name']) ? $val['name'] : '';
                        $tmp['before_value'] = isset($val['value']) ? $val['value'] : '';
                        $tmp['after_value'] = isset($after_data[$key]) && isset($after_data[$key]['value']) ? $after_data[$key]['value'] : '';
                        if (is_array($tmp['after_value']) && is_array($tmp['before_value']))
                        {
                            $diff1 = array_diff($tmp['after_value'], $tmp['before_value']);
                            $tmp['after_value'] = implode(',', $diff1);
                        }
                        else
                        {
                            if (is_array($tmp['after_value']))
                            {
                                $tmp['after_value'] = implode(',', $tmp['after_value']);
                            }
                        }
                        $diff_items[] = $tmp;
                    }
                }
                break;
            }
            // insert activity into db.
            $info = [ 'issue_id' => $issue_id, 'event_key' => $event_key, 'user' => $user, 'summary' => $diff_items, 'created_at' => time() ];
        }
        else
        {
            $info = [ 'issue_id' => $issue_id, 'event_key' => $event_key, 'user' => $user, 'summary' => $param, 'created_at' => time() ];
        }

        return DB::collection('activity_' . $project_key)->insertGetId($info);
    }

    /**
     * add notice queue.
     *
     * @param  string $project_key
     * @param  string $issue_id
     * @param  string $activity_id
     * @return void
     */
    public function putMQ($project_key, $issue_id, $activity_id)
    {
        DB::collection('mq')->insert([ 'project_key' => $project_key, 'issue_id' => $issue_id, 'activity_id' => $activity_id, 'created_at' => time() ]); 
    }
}
