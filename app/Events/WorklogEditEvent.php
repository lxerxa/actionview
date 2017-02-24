<?php
namespace App\Events;
use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WorklogDelEvent extends Event
{
    use SerializesModels;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($project_key, $issue_id, $worklog, $user)
    {
        $this->project_key = $project_key;
        $this->issue_id = $issue_id;
        $this->worklog = $worklog;
        $this->user = $user;
    }
    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
