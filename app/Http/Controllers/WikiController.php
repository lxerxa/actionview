<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\WikiEvent;
use App\Utils\File;
use DB;
use Zipper;

class WikiController extends Controller
{
    /**
     * search path.
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function searchPath(Request $request, $project_key)
    {
        $s =  $request->input('s');
        if (!$s)
        {
            return Response()->json(['ecode' => 0, 'data' => []]);
        }

        if ($s === '/')
        {
            return Response()->json(['ecode' => 0, 'data' => [ [ 'id' => '0', 'name' => '/' ] ] ]);
        }

        $query = DB::collection('wiki_' . $project_key)
            ->where('d', 1)
            ->where('del_flag', '<>', 1)
            ->where('name', 'like', '%' . $s . '%');

        $moved_path = $request->input('moved_path');
        if (isset($moved_path) && $moved_path)
        {
            $query->where('pt', '<>', $moved_path);
            $query->where('_id', '<>', $moved_path);
        }

        $directories = $query->take(20)->get(['name', 'pt']);

        $ret = [];
        foreach ($directories as $d)
        {
            $parents = [];
            $path = '';
            $ps = DB::collection('wiki_' . $project_key)
                ->whereIn('_id', $d['pt'])
                ->get([ 'name' ]);
            foreach ($ps as $val)
            {
                $parents[$val['_id']->__toString()] = $val['name'];
            }

            foreach ($d['pt'] as $pid)
            {
                if (isset($parents[$pid]))
                {
                    $path .= '/' . $parents[$pid];
                }
            }
            $path .= '/' . $d['name'];
            $ret[] = [ 'id' => $d['_id']->__toString(), 'name' => $path ];
        }
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($ret)]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $project_key, $directory)
    {
        $documents = [];
        $mode = 'list';
        $query = DB::collection('wiki_' . $project_key);

        $name = $request->input('name');
        if (isset($name) && $name)
        {
            $mode = 'search';
            $query = $query->where('name', 'like', '%' . $name . '%');
        }

        $updated_at = $request->input('updated_at');
        if (isset($updated_at) && $updated_at)
        {
            $mode = 'search';
            $query->where(function ($query) use ($updated_at) {
                $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
                $unit = substr($updated_at, -1);
                $val = abs(substr($updated_at, 0, -1));
                $query->where('created_at', '>=', strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))))
                    ->orwhere('updated_at', '>=', strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))));
            });
        }

        if ($directory !== '0')
        {
            $query = $query->where($mode === 'search' ? 'pt' : 'parent', $directory);
        }
        else
        {
            if ($mode === 'list')
            {
                $query = $query->where('parent', $directory);
            }
        }

        $query->where('del_flag', '<>', 1);
        $query->orderBy('d', 'desc')->orderBy('_id', 'desc');

        $limit = 1000; // fix me
        $query->take($limit);
        $documents = $query->get();

        $path = [];
        $home = [];
        if ($directory === '0')
        {
            $path[] = [ 'id' => '0', 'name' => 'root' ];
            if ($mode === 'list')
            {
                foreach ($documents as $doc)
                {
                    if ((!isset($doc['d']) || $doc['d'] != 1) && strtolower($doc['name']) === 'home')
                    {
                        $home = $doc;
                    }
                }
            }
        }
        else
        {
            $d = DB::collection('wiki_' . $project_key)
                ->where('_id', $directory)
                ->first();
            if ($d && isset($d['pt']))
            {
                $path = $this->getPathTreeDetail($project_key, $d['pt']);
            }
            $path[] = [ 'id' => $directory, 'name' => $d['name'] ];
        }

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($documents), 'options' => [ 'path' => $path, 'home' => parent::arrange($home) ] ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $project_key)
    {
        $d =  $request->input('d');
        if (isset($d) && $d == 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
            return $this->createFolder($request, $project_key);
        }
        else
        {
            return $this->createDoc($request, $project_key);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function createDoc(Request $request, $project_key)
    {
        $insValues = [];

        $parent = $request->input('parent');
        if (!isset($parent))
        {
            throw new \UnexpectedValueException('the parent directory can not be empty.', -11950);
        }
        $insValues['parent'] = $parent;

        if ($parent !== '0')
        {
            $isExists = DB::collection('wiki_' . $project_key)
                ->where('_id', $parent)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->exists();
            if (!$isExists)
            {
                throw new \UnexpectedValueException('the parent directory does not exist.', -11951);
            }
        }

        $name = $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11952);
        }
        $insValues['name'] = $name;

        $isExists = DB::collection('wiki_' . $project_key)
            ->where('parent', $parent)
            ->where('name', $name)
            ->where('d', '<>', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11953);
        }

        $contents = $request->input('contents');
        if (isset($contents) && $contents)
        {
            $insValues['contents'] = $contents;
        }

        $insValues['pt'] = $this->getPathTree($project_key, $parent);
        $insValues['version'] = 1;
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $insValues['created_at'] = time();
        $id = DB::collection('wiki_' . $project_key)->insertGetId($insValues);

        $isSendMsg = $request->input('isSendMsg') && true;
        Event::fire(new WikiEvent($project_key, $insValues['creator'], [ 'event_key' => 'create_wiki', 'isSendMsg' => $isSendMsg, 'data' => [ 'wiki_id' => $id->__toString() ] ]));

        return $this->show($request, $project_key, $id);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function createFolder(Request $request, $project_key)
    {
        $insValues = [];

        $parent = $request->input('parent');
        if (!isset($parent))
        {
            throw new \UnexpectedValueException('the parent directory can not be empty.', -11950);
        }
        $insValues['parent'] = $parent;

        if ($parent !== '0')
        {
            $isExists = DB::collection('wiki_' . $project_key)
                ->where('_id', $parent)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->exists();
            if (!$isExists)
            {
                throw new \UnexpectedValueException('the parent directory does not exist.', -11951);
            }
        }

        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11952);
        }
        $insValues['name'] = $name;

        $isExists = DB::collection('wiki_' . $project_key)
            ->where('parent', $parent)
            ->where('name', $name)
            ->where('d', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11953);
        }

        $insValues['pt'] = $this->getPathTree($project_key, $parent);
        $insValues['d'] = 1;
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $insValues['created_at'] = time();

        $id = DB::collection('wiki_' . $project_key)->insertGetId($insValues);

        $document = DB::collection('wiki_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
    }

    /**
     * check in the wiki.
     * @param  string  $project_key
     * @param  string  $id
     * @return array
     */
    public function checkin(Request $request, $project_key, $id)
    {
        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (isset($document['checkin']) && $document['checkin'])
        {
            throw new \UnexpectedValueException('the object has been locked.', -11955);
        }

        $checkin = []; 
        $checkin['user'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $checkin['at'] = time();

        DB::collection('wiki_' . $project_key)->where('_id', $id)->update(['checkin' => $checkin]);

        return $this->show($request, $project_key, $id);
    }

    /**
     * check out the wiki.
     * @param  string  $project_key
     * @param  string  $id
     * @return array
     */
    public function checkout(Request $request, $project_key, $id)
    {
        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (isset($document['checkin']) && !((isset($document['checkin']['user']) && $document['checkin']['user']['id'] == $this->user->id) || $this->isPermissionAllowed($project_key, 'manage_project')))
        {
            throw new \UnexpectedValueException('the object cannot been unlocked.', -11956);
        }

        DB::collection('wiki_' . $project_key)->where('_id', $id)->unset('checkin');

        return $this->show($request, $project_key, $id);
    }

    /**
     * get parent treee.
     * @param  string  $project_key
     * @param  array  $pt
     * @return array
     */
    public function getPathTreeDetail($project_key, $pt)
    {
        $parents = [];
        $ps = DB::collection('wiki_' . $project_key)
            ->whereIn('_id', $pt)
            ->get([ 'name' ]);
        foreach ($ps as $val)
        {
            $parents[$val['_id']->__toString()] = $val['name'];
        }

        $path = [];
        foreach ($pt as $pid)
        {
            if ($pid === '0')
            {
                $path[] = [ 'id' => '0', 'name' => 'root' ];
            }
            else if (isset($parents[$pid]))
            {
                $path[] = [ 'id' => $pid, 'name' => $parents[$pid] ];
            }
        }
        return $path;
    }

    /**
     * get parent treee.
     * @param  string  $project_key
     * @param  string  $directory
     * @return array
     */
    public function getPathTree($project_key, $directory)
    {
        $pt = [];
        if ($directory === '0')
        {
            $pt = [ '0' ];
        }
        else
        {
            $d = DB::collection('wiki_' . $project_key)
                ->where('_id', $directory)
                ->first();
            $pt = array_merge($d['pt'], [ $directory ]);
        }
        return $pt;
    }

    /**
     * get the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $project_key, $id)
    {
        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        $newest = [];
        $newest['name']       = $document['name'];
        $newest['editor']     = isset($document['editor']) ? $document['editor'] : $document['creator'];
        $newest['updated_at'] = isset($document['updated_at']) ? $document['updated_at'] : $document['created_at'];
        $newest['version']    = $document['version'];

        $v =  $request->input('v');
        if (isset($v) && intval($v) != $document['version'])
        {
            $w = DB::collection('wiki_version_' . $project_key)
                ->where('wid', $id)
                ->where('version', intval($v)) 
                ->first();
            if (!$w)
            {
                throw new \UnexpectedValueException('the version does not exist.', -11957);
            }

            $document['name']       = $w['name'];
            $document['contents']   = $w['contents'];
            $document['editor']     = $w['editor'];
            $document['updated_at'] = $w['updated_at'];
            $document['version']    = $w['version'];
        }
        
        $document['versions'] = DB::collection('wiki_version_' . $project_key)
            ->where('wid', $id)
            ->orderBy('_id', 'desc')
            ->get(['name', 'editor', 'updated_at', 'version']);
        array_unshift($document['versions'], $newest);

        $path = $this->getPathTreeDetail($project_key, $document['pt']);

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document), 'options' => [ 'path' => $path ]]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11952);
        }

        $old_document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$old_document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (isset($old_document['d']) && $old_document['d'] === 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project')) 
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else
        {
            if (isset($old_document['checkin']) && isset($old_document['checkin']['user']) && $old_document['checkin']['user']['id'] !== $this->user->id)
            {
                throw new \UnexpectedValueException('the object has been locked.', -11955);
            }
        }

        $updValues = [];
        if ($old_document['name'] !== $name)
        {
            $query = DB::collection('wiki_' . $project_key)
                ->where('parent', $old_document['parent'])
                ->where('name', $name)
                ->where('del_flag', '<>', 1);

            if (isset($old_document['d']) && $old_document['d'] === 1)
            {
                $query->where('d', 1);
            }
            else
            {
                $query->where('d', '<>', 1);
            }

            $isExists = $query->exists();
            if ($isExists)
            {
                throw new \UnexpectedValueException('the name cannot be repeated.', -11953);
            }

            $updValues['name'] = $name;
        }

        if (!isset($old_document['d']) || $old_document['d'] !== 1)
        {
            $contents = $request->input('contents');
            if (isset($contents) && $contents)
            {
                $updValues['contents'] = $contents;
            }

            if (isset($old_document['version']) && $old_document['version'])
            {
                $updValues['version'] = $old_document['version'] + 1;
            }
            else
            {
                $updValues['version'] = 2;
            }
        }
        
        $updValues['editor'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $updValues['updated_at'] = time();
        DB::collection('wiki_' . $project_key)->where('_id', $id)->update($updValues);

        // record the version
        if (!isset($old_document['d']) || $old_document['d'] !== 1)
        {
            // unlock the wiki
            DB::collection('wiki_' . $project_key)->where('_id', $id)->unset('checkin'); 
            // record versions 
            $this->recordVersion($project_key, $old_document);

            $isSendMsg = $request->input('isSendMsg') && true;
            Event::fire(new WikiEvent($project_key, $updValues['editor'], [ 'event_key' => 'edit_wiki', 'isSendMsg' => $isSendMsg, 'data' => [ 'wiki_id' => $id ] ]));

            return $this->show($request, $project_key, $id);
        }
        else
        {
            $document = DB::collection('wiki_' . $project_key)->where('_id', $id)->first();
            return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
        }
    }

    /**
     * record the last version.
     *
     * @param  array  $document
     * @return \Illuminate\Http\Response
     */
    public function recordVersion($project_key, $document)
    {
        $insValues = [];

        $insValues['wid']         = $document['_id']->__toString();
        $insValues['name']        = isset($document['name']) ? $document['name'] : '';
        $insValues['contents']    = isset($document['contents']) ? $document['contents'] : '';
        $insValues['version']     = isset($document['version']) ? $document['version'] : 1;
        $insValues['editor']      = isset($document['editor']) ? $document['editor'] : $document['creator'];
        $insValues['updated_at']  = isset($document['updated_at']) ? $document['updated_at'] : $document['created_at'];

        DB::collection('wiki_version_' . $project_key)->insert($insValues);
    }

    /**
     * copy the document.
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function copy(Request $request, $project_key)
    {
        $id =  $request->input('id');
        if (!isset($id) || !$id)
        {
            throw new \UnexpectedValueException('the copy object can not be empty.', -11960);
        }

        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11952);
        }

        $dest_path =  $request->input('dest_path');
        if (!isset($dest_path))
        {
            throw new \UnexpectedValueException('the dest directory can not be empty.', -11961);
        }

        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('d', '<>', 1)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the copy object does not exist.', -11962);
        }

        $dest_directory = [];
        if ($dest_path !== '0')
        {
            $dest_directory = DB::collection('wiki_' . $project_key)
                ->where('_id', $dest_path)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->first();
            if (!$dest_directory)
            {
                throw new \UnexpectedValueException('the dest directory does not exist.', -11963);
            }
        }

        $isExists = DB::collection('wiki_' . $project_key)
            ->where('parent', $dest_path)
            ->where('name', $name)
            ->where('d', '<>', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11953);
        }

        $insValues = [];
        $insValues['name'] = $name;
        $insValues['parent'] = $dest_path;
        $insValues['pt'] = array_merge(isset($dest_directory['pt']) ? $dest_directory['pt'] : [], [$dest_path]);

        //$insValues['size']    = $document['size'];
        //$insValues['type']    = $document['type'];
        //$insValues['index']   = $document['index'];

        $insValues['version'] = 1;
        $insValues['contents'] = isset($document['contents']) ? $document['contents'] : '';
        $insValues['attachments'] = isset($document['attachments']) ? $document['attachments'] : [];
            
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $insValues['created_at'] = time();

        $new_id = DB::collection('wiki_' . $project_key)->insertGetId($insValues);         

        $document = DB::collection('wiki_' . $project_key)->where('_id', $new_id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
    }

    /**
     * move the document.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $project_key
     * @return \Illuminate\Http\Response
     */
    public function move(Request $request, $project_key)
    {
        $id =  $request->input('id');
        if (!isset($id) || !$id)
        {
            throw new \UnexpectedValueException('the move object can not be empty.', -11964);
        }

        $dest_path =  $request->input('dest_path');
        if (!isset($dest_path))
        {
            throw new \UnexpectedValueException('the dest directory can not be empty.', -11965);
        }

        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the move object does not exist.', -11966);
        }

        if (isset($document['d']) && $document['d'] === 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        $dest_directory = [];
        if ($dest_path !== '0')
        {
            $dest_directory = DB::collection('wiki_' . $project_key)
                ->where('_id', $dest_path)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->first();
            if (!$dest_directory)
            {
                throw new \UnexpectedValueException('the dest directory does not exist.', -11967);
            }
        }

        $isExists = DB::collection('wiki_' . $project_key)
            ->where('parent', $dest_path)
            ->where('name', $document['name'])
            ->where('d', isset($document['d']) && $document['d'] === 1 ? '=' : '<>', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11953);
        }

        $updValues = [];
        $updValues['parent'] = $dest_path;
        $updValues['pt'] = array_merge(isset($dest_directory['pt']) ? $dest_directory['pt'] : [], [$dest_path]);
        DB::collection('wiki_' . $project_key)->where('_id', $id)->update($updValues);

        if (isset($document['d']) && $document['d'] === 1)
        {
            $subs = DB::collection('wiki_' . $project_key)
                ->where('pt', $id)
                ->where('del_flag', '<>', 1)
                ->get();
             foreach ($subs as $sub)
             {
                 $pt = isset($sub['pt']) ? $sub['pt'] : [];
                 $pind = array_search($id, $pt);
                 if ($pind !== false)
                 {
                     $tail = array_slice($pt, $pind);
                     $pt = array_merge($updValues['pt'], $tail);
                     DB::collection('wiki_' . $project_key)->where('_id', $sub['_id']->__toString())->update(['pt' => $pt]);
                 }
             }
        }

        $document = DB::collection('wiki_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $project_key
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $id)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (isset($document['d']) && $document['d'] === 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        DB::collection('wiki_' . $project_key)->where('_id', $id)->update([ 'del_flag' => 1 ]);

        if (isset($document['d']) && $document['d'] === 1)
        {
            DB::collection('wiki_' . $project_key)->whereRaw([ 'pt' => $id ])->update([ 'del_flag' => 1 ]);
        }

        $user = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        Event::fire(new WikiEvent($project_key, $user, [ 'event_key' => 'delete_wiki', 'wiki_id' => $id ]));

        return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ]]);
    }

    /**
     * Upload file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $wid
     * @param  String  $fid
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request, $project_key, $wid)
    {
        set_time_limit(0);

        if (!is_writable(config('filesystems.disks.local.root', '/tmp')))
        {
            throw new \UnexpectedValueException('the user has not the writable permission to the directory.', -15103);
        }

        $fields = array_keys($_FILES);
        $field = array_pop($fields);
        if (empty($_FILES) || $_FILES[$field]['error'] > 0)
        {
            throw new \UnexpectedValueException('upload file errors.', -11959);
        }

        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $wid)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        $basename = md5(microtime() . $_FILES[$field]['name']);
        $sub_save_path = config('filesystems.disks.local.root', '/tmp') . '/' . substr($basename, 0, 2) . '/';
        if (!is_dir($sub_save_path))
        {
            @mkdir($sub_save_path);
        }
        move_uploaded_file($_FILES[$field]['tmp_name'], $sub_save_path . $basename);

        $data = [];

        $data['name'] = $_FILES[$field]['name'];;
        $data['size']    = $_FILES[$field]['size'];
        $data['type']    = $_FILES[$field]['type'];
        $data['id'] = $data['index']   = $basename;

        $data['uploader'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $data['uploaded_at'] = time();

        $attachments = [];
        if (isset($document['attachments']) && $document['attachments'])
        {
            $attachments = $document['attachments'];
        }

        $attachments[] = $data;
        DB::collection('wiki_' . $project_key)->where('_id', $wid)->update([ 'attachments' => $attachments ]);

        return Response()->json(['ecode' => 0, 'data' => $data]);
    }

    /**
     * remove file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $wid
     * @return \Illuminate\Http\Response
     */
    public function remove(Request $request, $project_key, $wid, $fid)
    {
        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $wid)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (!isset($document['attachments']) || !$document['attachments'])
        {
            throw new \UnexpectedValueException('the file does not exist.', -11958);
        }

        $new_attachments = [];
        foreach ($document['attachments'] as $a)
        {
            if ($a['id'] !== $fid)
            {
                $new_attachments[] = $a;
            }
        }

        DB::collection('wiki_' . $project_key)->where('_id', $wid)->update([ 'attachments' => $new_attachments ]);
        return Response()->json(['ecode' => 0, 'data' => [ 'id' => $fid ]]);
    }

    /**
     * Download file or directory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $wid
     * @return \Illuminate\Http\Response
     */
    public function download2(Request $request, $project_key, $wid)
    {
        set_time_limit(0);

        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $wid)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (!isset($document['attachments']) || !$document['attachments'])
        {
            throw new \UnexpectedValueException('the file does not exist.', -11958);
        }

        $this->downloadFolder($document['name'], $document['attachments']);
    }

    /**
     * Download file or directory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $wid
     * @param  String  $fid
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, $project_key, $wid, $fid)
    {
        set_time_limit(0);

        $document = DB::collection('wiki_' . $project_key)
            ->where('_id', $wid)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11954);
        }

        if (!isset($document['attachments']) || !$document['attachments'])
        {
            throw new \UnexpectedValueException('the file does not exist.', -11958);
        }

        $isExists = false;
        foreach ($document['attachments'] as $file)
        {
            if (isset($file['id']) && $file['id'] === $fid) 
            {
                $isExists = true;
                break;
            }
        }

        if (!$isExists)
        {
            throw new \UnexpectedValueException('the file does not exist.', -11958);
        }

        $this->downloadFile($file['name'], $file['index']);
    }

    /**
     * Download file.
     *
     * @param  String  $name
     * @param  array   $attachments
     * @return \Illuminate\Http\Response
     */
    public function downloadFolder($name, $attachments)
    {
        setlocale(LC_ALL, 'zh_CN.UTF-8'); 

        $basepath = '/tmp/' . md5($this->user->id . microtime());
        @mkdir($basepath);

        $fullpath = $basepath . '/' . $name;
        @mkdir($fullpath);

        foreach ($attachments as $attachment)
        {
            $filepath = config('filesystems.disks.local.root', '/tmp') . '/' . substr($attachment['index'], 0, 2);
            $filename = $filepath . '/' . $attachment['index'];
            if (file_exists($filename))
            {
                @copy($filename, $fullpath . '/' . $attachment['name']);
            }
        }

        $fname = $basepath . '/' . $name . '.zip';
        Zipper::make($fname)->folder($name)->add($basepath . '/' . $name);
        Zipper::close();

        File::download($fname, $name . '.zip');

        exec('rm -rf ' . $basepath);
    }

    /**
     * Download file.
     *
     * @param  String  $name
     * @param  String  $index
     * @return \Illuminate\Http\Response
     */
    public function downloadFile($name, $index)
    {
        $filepath = config('filesystems.disks.local.root', '/tmp') . '/' . substr($index, 0, 2);
        $filename = $filepath . '/' . $index;
        if (!file_exists($filename))
        {
            throw new \UnexpectedValueException('file does not exist.', -11958);
        }

        File::download($filename, $name);
    }
}
