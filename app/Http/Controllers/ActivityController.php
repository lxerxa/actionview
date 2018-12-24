<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Project\Provider;
use DB;

use Sentinel;

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

        $query = DB::collection('activity_' . $project_key);

        $category = $request->input('category');
        if (isset($category) && $category != 'all')
        {
            $query->where('event_key', 'like', '%' . $category);
        }

        $offset_id = $request->input('offset_id');
        if (isset($offset_id))
        {
            $query = $query->where('_id', '<', $offset_id);
        }

        $query->whereRaw([ 'issue_id' => [ '$exists' => 1 ] ]);

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
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 ];

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
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 ];
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
                    'del_flg' => isset($issue['del_flg']) ? $issue['del_flg'] : 0 ];
                $cache_issues[$activity['issue_id']] = $issue;
            }
        }
        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($activities), 'options' => [ 'current_time' => time() ] ]);
    }
}
