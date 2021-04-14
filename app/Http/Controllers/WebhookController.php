<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Eloquent\ExternalUsers;

use App\WebHook\GitLabPush;
use App\WebHook\GitHubPush;

use DB;

class WebhookController extends Controller
{
    /**
     * exec the api from external sys.
     *
     * @return \Illuminate\Http\Response
     */
    public function exec($type, $project_key)
    {

        if (!in_array($type, ['gitlab', 'github']))
        {
            return Response()->json([ 'ecode' => -2, 'emsg' => 'the request url has error.' ]);
        }

        $external_user = ExternalUsers::where('project_key', $project_key)
            ->where('user', $type)
            ->first();
        if (!$external_user)
        {
            return Response()->json([ 'ecode' => -3, 'emsg' => 'the user has not been used.' ]);
        } 
        else if ($external_user->status !== 'enabled')
        {
            return Response()->json([ 'ecode' => -4, 'emsg' => 'the user has been disabled.' ]);
        }

        $payload = file_get_contents('php://input');
        if ($type === 'gitlab')
        {
            if ($_SERVER['HTTP_X_GITLAB_EVENT'] !== 'Push Hook')
            { 
                return Response()->json([ 'ecode' => -5, 'emsg' => 'the event has error.' ]);
            }

            $token = $external_user->pwd ?: '';
            if (!$token || $token !== $_SERVER['HTTP_X_GITLAB_TOKEN']) 
            {
                return Response()->json([ 'ecode' => -1, 'emsg' => 'the token has error.' ]);
            }

            $push = new GitLabPush(json_decode($payload, true));
            $push->insCommits($project_key);
        }
        else if ($type === 'github')
        {
            if ($_SERVER['HTTP_X_GITHUB_EVENT'] !== 'push')
            {
                return Response()->json([ 'ecode' => -5, 'emsg' => 'the event has error.' ]);
            }

            $token = $external_user->pwd ?: '';
            if (!$token)
            {
                return Response()->json([ 'ecode' => -1, 'emsg' => 'the token can not be empty.' ]);
            }

            $signature = 'sha1=' . hash_hmac('sha1', $payload, $token);
            if ($signature !== $_SERVER['HTTP_X_HUB_SIGNATURE'])
            {
                return Response()->json([ 'ecode' => -1, 'emsg' => 'the token has error.' ]);
            }

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
