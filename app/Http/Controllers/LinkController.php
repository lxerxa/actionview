<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;

class LinkController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $values = [];
        $src = $request->input('src');
        if (!$src)
        {
            throw new \UnexpectedValueException('the src issue value can not be empty.', -10002);
        }
        $values['src'] = $src;

        $relations = ['blocks', 'is blocked by', 'clones', 'is cloned by', 'duplicates', 'is duplicated by', 'relates to'];
        $relation = $request->input('relation');
        if (!$relation || !in_array($relation, $relations)) {
            throw new \UnexpectedValueException('the relation value has error.', -10002);
        }
        $values['relation'] = $relation;

        $dest = $request->input('dest');
        if (!$dest)
        {
            throw new \UnexpectedValueException('the dest issue value can not be empty.', -10002);
        }
        $values['dest'] = $dest;

        $values['created_at'] = time();
        $values['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name ];

        $table = 'issue_link_' . $project_key;

        $isExists = DB::collection($table)->where('src', $src)->where('dest', $dest)->exists();
        if ($isExists || DB::collection($table)->where('dest', $src)->where('src', $dest)->exists())
        {
            throw new \UnexpectedValueException('the relation of two issues has been exists.', -10002);
        }

        $id = DB::collection($table)->insertGetId($values);

        $link = $values;
        $link['_id'] = $id;

        $src_issue = DB::collection('issue_' . $project_key)->where('_id', $src)->first();
        $link['src'] = array_only($src_issue, ['_id', 'no', 'type', 'title']);

        $dest_issue = DB::collection('issue_' . $project_key)->where('_id', $dest)->first();
        $link['dest'] = array_only($dest_issue, ['_id', 'no', 'type', 'title']); 

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($link) ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $table = 'issue_link_' . $project_key;
        $link = DB::collection($table)->where('_id', $id)->first();
        if (!$link)
        {
            throw new \UnexpectedValueException('the link does not exist or is not in the project.', -10002);
        }

        DB::collection($table)->where('_id', $id)->delete();

        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
