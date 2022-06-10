<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB;
use Sentinel;

use App\Project\Eloquent\Version;
use App\Project\Eloquent\Sprint;


class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key)
    {
        $cache_issues = [];
        $cache_wikis = [];

        $query = DB::collection('activity_' . $project_key);

        $category = $request->input('category');
        if (isset($category) && $category != 'all')
        {
            if ($category == 'issue')
            {
                $query->whereRaw([ 'issue_id' => [ '$exists' => 1 ] ]);
            }
            else
            {
                $query->where('event_key', 'like', '%' . $category);
            }
        }

        $offset_id = $request->input('offset_id');
        if (isset($offset_id))
        {
            $query = $query->where('_id', '<', $offset_id);
        }

        $query->whereRaw([ 
            '$or' => [ 
                [ 'issue_id' => [ '$exists' => 1 ] ], 
                [ 'wiki_id' => [ '$exists' => 1 ] ], 
                [ 'document_id' => [ '$exists' => 1 ] ], 
                [ 'version_id' => [ '$exists' => 1 ] ], 
                [ 'sprint_no' => [ '$exists' => 1 ] ]
            ]
        ]);

        $query->orderBy('_id', 'desc');

        $limit = $request->input('limit');
        if (!isset($limit))
        {
            $limit = 30;
        }
        $query->take(intval($limit));

        $avatars = [];
        $activities = $query->get();
        foreach ($activities as $key => $activity)
        {
            if (!array_key_exists($activity['user']['id'], $avatars))
            {
                $user = Sentinel::findById($activity['user']['id']);
                $avatars[$activity['user']['id']] = isset($user->avatar) ? $user->avatar : '';
            }
            $activities[$key]['user']['avatar'] = $avatars[$activity['user']['id']];

            if ($activity['event_key'] == 'create_link' || $activity['event_key'] == 'del_link')
            {
                $activities[$key]['issue_link'] = [];
                if (isset($cache_issues[$activity['issue_id']]))
                {
                    $issue = $cache_issues[$activity['issue_id']]; 
                }
                else
                {
                    $issue = DB::collection('issue_' . $project_key)->where('_id', $activity['issue_id'])->first();
                }
                $activities[$key]['issue_link'][ 'src'] = [ 
                    'id' => $activity['issue_id'], 
                    'no' => $issue['no'], 
                    'title' => isset($issue['title']) ? $issue['title'] : '', 
                    'state' => isset($issue['state']) ? $issue['state'] : '', 
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 
                ];

                $activities[$key]['issue_link']['relation'] = $activity['data']['relation'];

                if (isset($cache_issues[$activity['data']['dest']]))
                {
                    $issue = $cache_issues[$activity['data']['dest']]; 
                }
                else
                {
                    $issue = DB::collection('issue_' . $project_key)->where('_id', $activity['data']['dest'])->first();
                }
                $activities[$key]['issue_link']['dest'] = [ 
                    'id' => $activity['data']['dest'], 
                    'no' => $issue['no'], 
                    'title' => isset($issue['title']) ? $issue['title'] : '', 
                    'state' => isset($issue['state']) ? $issue['state'] : '', 
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 
                ];
            }
            else if (isset($activity['issue_id']))
            {
                if (isset($cache_issues[$activity['issue_id']]))
                {
                    $issue = $cache_issues[$activity['issue_id']]; 
                }
                else
                {
                    $issue = DB::collection('issue_' . $project_key)->where('_id', $activity['issue_id'])->first();
                }
                $activities[$key]['issue'] = [ 
                    'id' => $activity['issue_id'], 
                    'no' => $issue['no'], 
                    'title' => isset($issue['title']) ? $issue['title'] : '', 
                    'state' => isset($issue['state']) ? $issue['state'] : '', 
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 
                ];
                $cache_issues[$activity['issue_id']] = $issue;
            }
            else if (isset($activity['version_id']))
            {
                $version = Version::find($activity['version_id']);

                $activities[$key]['version'] = [
                    'id' => $activity['version_id'],
                    'name' => isset($version->name) ? $version->name : '',
                    'del_flag' => $version ? 0 : 1 
                ];
            }
            else if (isset($activity['document_id']))
            {
                $document = DB::collection('document_' . $project_key)->where('_id', $activity['document_id'])->first();

                $activities[$key]['document'] = [
                    'id' => $activity['document_id'],
                    'name' => isset($document['name']) ? $document['name'] : '',
                    'd' => isset($document['d']) ? $document['d'] : '',
                    'del_flag' => isset($document['del_flag']) ? $document['del_flag'] : 0
                ];
            }
            else if (isset($activity['wiki_id']))
            {
                $wiki_id = $activity['wiki_id'];
                if (isset($cache_wikis[$wiki_id]))
                {
                    $wiki = $cache_wikis[$wiki_id];
                }
                else
                {
                    $wiki = DB::collection('wiki_' . $project_key)->where('_id', $wiki_id)->first();
                }

                $activities[$key]['wiki'] = [
                    'id' => $wiki_id,
                    'name' => array_get($wiki, 'name', ''),
                    'parent' => array_get($wiki, 'parent', ''),
                    'd' => isset($wiki['d']) ? $wiki['d'] : '',
                    'del_flag' => array_get($wiki, 'del_flag', 0) 
                ];
                $cache_wikis[$wiki_id] = $wiki;
            }
            else if (isset($activity['sprint_no']))
            {
                $sprint = Sprint::where('project_key', $project_key)
                    ->where('no', $activity['sprint_no'])
                    ->first();

                $activities[$key]['sprint'] = [
                    'no' => $activity['sprint_no'],
                    'name' => isset($sprint->name) ? $sprint->name : ('Sprint ' . $activity['sprint_no']),
                ];
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($activities), 'options' => [ 'current_time' => time() ] ]);
    }
}
