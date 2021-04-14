<?php

namespace App\WebHook;

use App\Workflow\Workflow;
use DB;
use Exception;

class GitPush 
{
    // the push git repository
    protected $repo = [];

    // the push branch
    protected $branch = '';

    // the commits in the push
    protected $commits = [];

    // the pusher
    protected $pusher = []; 

    // the after 
    protected $after = '';

    // the before
    protected $before = '';

    // construct
    public function __construct($contents)
    {
        $this->parse($contents);
    }

    public function parse($contents)
    {
    }

    protected function getRepo()
    {
        return $this->repo;
    }

    protected function getBranch()
    {
        return $this->branch;
    }

    protected function getCommits()
    {
        return $this->commits;
    }

    protected function getPusher()
    {
        return $this->pusher;
    }

    protected function getBefore()
    {
        return $this->before;
    }

    protected function getAfter()
    {
        return $this->after;
    }

    /**
     * arrange the data format.
     *
     * @param  number $issue_no
     * @param  array  $commit
     * @return array
     */
    public function arrangeCommit($commit)
    {
        return $commit;
    }

    /**
     * exec the workflow.
     *
     * @param  string  $project_key
     * @param  number  $issue_no
     * @param  number  $action_id
     * @param  string  $caller
     * @return void
     */
    public function execWorkflow($project_key, $issue, $action_id, $caller)
    {
        $issue_id = $issue['_id']->__toString();
        $entry_id = isset($issue['entry_id']) ? $issue['entry_id'] : '';
        if (!$entry_id)
        {
            return;
        }

        try {
            $entry = new Workflow($entry_id);
            $entry->doAction($action_id, [ 'project_key' => $project_key, 'issue_id' => $issue_id, 'caller' => $caller ]);
        } catch (Exception $e) {
            throw new Exception('the executed action has error.', -11115);
        }
        return;
    }

    /**
     * add git commits to the issue.
     *
     * @param  string $project_key
     * @param  array  $commits
     * @return void
     */
    public function insCommits($project_key)
    {
        $table = 'git_commits_' . $project_key;
        $issue_table = 'issue_' . $project_key;

        $commits = $this->getCommits();
        foreach($commits as $commit)
        {
            $new_commit = $this->arrangeCommit($commit);

            $issue_data = $this->relateIssue($project_key, $new_commit['message']);
            if ($issue_data === false)
            {
                continue;
            }
            $issue_no = $issue_data['no'];
            $action   = $issue_data['action'];

            $issue = DB::collection($issue_table)->where('no', $issue_no)->where('del_flg', '<>', 1)->first();
            if (!$issue)
            {
                return;
            }

            // insert the commit to issue 
            DB::collection($table)->insert($new_commit + [ 'issue_id' => $issue['_id']->__toString() ]);
            // transimit the issue status
            if ($action > 0 && isset($new_commit['author']['id']) && $new_commit['author']['id'])
            {
                $this->execWorkflow($project_key, $issue, $action, $new_commit['author']['id']);
            }
        }
        return;
    }

    /**
     * check if the commit relate to issue.
     *
     * @param  string  $project_key
     * @param  array  $commit
     * @return mixed
     */
    public function relateIssue($project_key, $message)
    {
        if (!$message)
        {
            return false;
        }

        $messages = explode(' ', trim($message));
        $prefix = array_shift($messages);
        if (strpos($prefix, '-') === false)
        {
            return false;
        }

        $marks = explode('-', $prefix);
        if ($marks[0] != $project_key)
        {
            return false;
        }

        if (!$marks[1] || !is_numeric($marks[1]) || strpos($marks[1], '.') !== false)
        {
            return false;
        }
        $issue_no = intval($marks[1]);

        $action = 0;
        if (isset($marks[2]) && $marks[2])
        {
            $action = is_numeric($marks[2]) && strpos($marks[2], '.') === false ? intval($marks[2]) : 0;
        }

        return [ 'no' => $issue_no, 'action' => $action ];
    }
}
