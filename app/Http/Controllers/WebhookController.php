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

$payload = <<< EOF
{
  "object_kind": "push",
  "event_name": "push",
  "before": "4d0c14dfe53a77df8b0df2aaaa91343612bff10d",
  "after": "389d9215e3b4c33d4e838f6554a16ea54bea7911",
  "ref": "refs/heads/develop",
  "checkout_sha": "389d9215e3b4c33d4e838f6554a16ea54bea7911",
  "message": null,
  "user_id": 67,
  "user_name": "liuxuyjy",
  "user_email": "liuxuyjy@chinamobile.com",
  "user_avatar": null,
  "project_id": 71,
  "project": {
    "name": "osns-server",
    "description": "",
    "web_url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server",
    "avatar_url": null,
    "git_ssh_url": "git@dev.cmri.cn:liuxuyjy/osns-server.git",
    "git_http_url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server.git",
    "namespace": "liuxuyjy",
    "visibility_level": 0,
    "path_with_namespace": "liuxuyjy/osns-server",
    "default_branch": "develop",
    "homepage": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server",
    "url": "git@dev.cmri.cn:liuxuyjy/osns-server.git",
    "ssh_url": "git@dev.cmri.cn:liuxuyjy/osns-server.git",
    "http_url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server.git"
  },
  "commits": [
    {
      "id": "e22008b69845b8523785041e2b5bfbcc7afa1355",
      "message": "demo-2 aaaabbbb\n",
      "timestamp": "2018-09-10T13:43:52+08:00",
      "url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server/commit/e22008b69845b8523785041e2b5bfbcc7afa1355",
      "author": {
        "name": "w18037143856",
        "email": "hongzhong@actionview.cn"
      },
      "added": [],
      "modified": [
        "gulp.sh"
      ],
      "removed": []
    },
    {
      "id": "389d9215e3b4c33d4e838f6554a16ea54bea7911",
      "message": "demo-2-2 ccccdddd\n",
      "timestamp": "2018-09-10T13:43:59+08:00",
      "url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server/commit/389d9215e3b4c33d4e838f6554a16ea54bea7911",
      "author": {
        "name": "w18037143856",
        "email": "yibing@actionview.cn"
      },
      "added": [],
      "modified": [
        "README.md"
      ],
      "removed": []
    }
  ],
  "total_commits_count": 2,
  "repository": {
    "name": "osns-server",
    "url": "git@dev.cmri.cn:liuxuyjy/osns-server.git",
    "description": "",
    "homepage": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server",
    "git_http_url": "http://dev.cmri.cn/gitlab/liuxuyjy/osns-server.git",
    "git_ssh_url": "git@dev.cmri.cn:liuxuyjy/osns-server.git",
    "visibility_level": 0
  }
}
EOF;


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
