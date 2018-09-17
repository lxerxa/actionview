<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\WebHook\GitLabPush;
use App\WebHook\GitHubPush;

class WebhookController extends Controller
{
    /**
     * exec the api from external sys.
     *
     * @return \Illuminate\Http\Response
     */
    public function exec($type, $project_key)
    {
        $payload = file_get_contents('php://input', 'r');

        if ($type === 'gitlab')
        {
            if ($_SERVER['HTTP_X_GITLAB_EVENT'] !== 'Push Hook')
            { 
                return;
            }

            $token = env('GITLAB_WEBHOOK_TOKEN', '');
            if (!$token || $token !== $_SERVER['HTTP_X_GITLAB_TOKEN']) 
            {
                Response()->json([ 'ecode' => -1, 'emsg': 'token error.' ]);
            }

            $payload = str_replace([ "\r\n", "\r", "\n", "\t" ], [ '<br/>', '<br/>', '<br/>', ' ' ], $payload);
            $push = new GitLabPush(json_decode($payload, true));
            $push->insCommits($project_key);
        }
        else if ($type === 'github')
        {
            if ($_SERVER['HTTP_X_GITHUB_EVENT'] !== 'push')
            {
                return;
            }

            $token = env('GITHUB_WEBHOOK_TOKEN', '');
            if (!$token)
            {
                Response()->json([ 'ecode' => -2, 'emsg': 'token can not empty.' ]);
            }

            $signature = hash_hmac('sha1', $payload, $token);
            if ($signature !== $_SERVER['HTTP_X_HUB_SIGNATURE'])
            {
                Response()->json([ 'ecode' => -1, 'emsg': 'token error.' ]);
            }

            $payload = str_replace([ "\r\n", "\r", "\n", "\t" ], [ '<br/>', '<br/>', '<br/>', ' ' ], $payload);
            $push = new GitHubPush(json_decode($payload, true));
            $push->insCommits($project_key);
        }
        exit;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key, $issue_id)
    {
        $sort = ($request->input('sort') === 'asc') ? 'asc' : 'desc';

        $commits = DB::collection('git_commits_' . $project_key)
            ->where('issue_id', $issue_id)
            ->orderBy('committed_at', $sort)
            ->get();

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($commits), 'options' => [ 'current_time' => time() ] ]);
    }
}
