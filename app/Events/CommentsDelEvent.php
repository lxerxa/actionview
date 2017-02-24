<?php
namespace App\Events;
use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CommentsDelEvent extends Event
{
    use SerializesModels;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($project_key, $issue_id, $comments, $user)
    {
        $this->project_key = $project_key;
        $this->issue_id = $issue_id;
        $this->comments = $comments;
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
