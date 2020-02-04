<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\DocumentEvent;
use App\Acl\Acl;
use DB;
use App\Utils\File;

use Zipper;

class DocumentController extends Controller
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

        $query = DB::collection('document_' . $project_key)
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
            $ps = DB::collection('document_' . $project_key)
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
    public function getOptions(Request $request, $project_key)
    {
        $uploaders = DB::collection('document_' . $project_key)
            ->where('del_flag', '<>' , 1)
            ->distinct('uploader')
            ->get([ 'uploader' ]);

        return Response()->json(['ecode' => 0, 'data' => [ 'uploader' => $uploaders ]]);
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
        $query = DB::collection('document_' . $project_key);

        $uploader_id = $request->input('uploader_id');
        if (isset($uploader_id) && $uploader_id)
        {
            $mode = 'search';
            $query->where('uploader.id', $uploader_id);
        }

        $name = $request->input('name');
        if (isset($name) && $name)
        {
            $mode = 'search';
            $query = $query->where('name', 'like', '%' . $name . '%');
        }

        $uploaded_at = $request->input('uploaded_at');
        if (isset($uploaded_at) && $uploaded_at)
        {
            $mode = 'search';
            $unitMap = [ 'w' => 'week', 'm' => 'month', 'y' => 'year' ];
            $unit = substr($uploaded_at, -1);
            $val = abs(substr($uploaded_at, 0, -1));
            $query->where('uploaded_at', '>=', strtotime(date('Ymd', strtotime('-' . $val . ' ' . $unitMap[$unit]))));
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
        if ($directory === '0')
        {
            $path[] = [ 'id' => '0', 'name' => 'root' ];
        }
        else
        {
            $path[] = [ 'id' => '0', 'name' => 'root' ];
            $d = DB::collection('document_' . $project_key)
                ->where('_id', $directory)
                ->first();
            if ($d && isset($d['pt']))
            {
                $parents = [];
                $ps = DB::collection('document_' . $project_key)
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
                        $path[] = [ 'id' => $pid, 'name' => $parents[$pid] ];
                    }
                }
            }
            $path[] = [ 'id' => $directory, 'name' => $d['name'] ];
        }

        return Response()->json([ 'ecode' => 0, 'data' => parent::arrange($documents), 'options' => [ 'path' => $path ] ]);
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
            throw new \UnexpectedValueException('the parent directory can not be empty.', -11905);
        }
        $insValues['parent'] = $parent;

        if ($parent !== '0')
        {
            $isExists = DB::collection('document_' . $project_key)
                ->where('_id', $parent)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->exists();
            if (!$isExists)
            {
                throw new \UnexpectedValueException('the parent directory does not exist.', -11906);
            }
        }

        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11900);
        }
        $insValues['name'] = $name;

        $isExists = DB::collection('document_' . $project_key)
            ->where('parent', $parent)
            ->where('name', $name)
            ->where('d', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11901);
        }

        $insValues['pt'] = $this->getParentTree($project_key, $parent);
        $insValues['d'] = 1;
        $insValues['creator'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $insValues['created_at'] = time();

        $id = DB::collection('document_' . $project_key)->insertGetId($insValues);

        $document = DB::collection('document_' . $project_key)->where('_id', $id)->first();
        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
    }

    /**
     * get parent treee.
     * @param  string  $project_key
     * @param  string  $directory
     * @return array
     */
    public function getParentTree($project_key, $directory)
    {
        $pt = [];
        if ($directory === '0')
        {
            $pt = [ '0' ];
        }
        else
        {
            $d = DB::collection('document_' . $project_key)
                ->where('_id', $directory)
                ->first();
            $pt = array_merge($d['pt'], [ $directory ]);
        }
        return $pt;
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
        $name = $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11900);
        }

        $old_document = DB::collection('document_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$old_document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11902);
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
            if (!$this->isPermissionAllowed($project_key, 'manage_project') && $old_document['uploader']['id'] !== $this->user->id) 
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        if ($old_document['name'] !== $name)
        {
            $query = DB::collection('document_' . $project_key)
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
                throw new \UnexpectedValueException('the name cannot be repeated.', -11901);
            }
        }

        DB::collection('document_' . $project_key)->where('_id', $id)->update([ 'name' => $name ]);
        $new_document = DB::collection('document_' . $project_key)->where('_id', $id)->first();

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($new_document)]);
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
            throw new \UnexpectedValueException('the move object can not be empty.', -11911);
        }

        $dest_path =  $request->input('dest_path');
        if (!isset($dest_path))
        {
            throw new \UnexpectedValueException('the dest directory can not be empty.', -11912);
        }

        $document = DB::collection('document_' . $project_key)
            ->where('_id', $id)
            ->where('del_flag', '<>', 1)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the move object does not exist.', -11913);
        }

        if (isset($document['d']) && $document['d'] === 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project') && $document['uploader']['id'] !== $this->user->id)
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        $dest_directory = [];
        if ($dest_path !== '0')
        {
            $dest_directory = DB::collection('document_' . $project_key)
                ->where('_id', $dest_path)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->first();
            if (!$dest_directory)
            {
                throw new \UnexpectedValueException('the dest directory does not exist.', -11914);
            }
        }

        $isExists = DB::collection('document_' . $project_key)
            ->where('parent', $dest_path)
            ->where('name', $document['name'])
            ->where('d', isset($document['d']) && $document['d'] === 1 ? '=' : '<>', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11901);
        }

        $updValues = [];
        $updValues['parent'] = $dest_path;
        $updValues['pt'] = array_merge(isset($dest_directory['pt']) ? $dest_directory['pt'] : [], [$dest_path]);
        DB::collection('document_' . $project_key)->where('_id', $id)->update($updValues);

        if (isset($document['d']) && $document['d'] === 1) 
        {
            $subs = DB::collection('document_' . $project_key)
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
                     DB::collection('document_' . $project_key)->where('_id', $sub['_id']->__toString())->update(['pt' => $pt]);
                 }
             }
        }

        $document = DB::collection('document_' . $project_key)->where('_id', $id)->first();
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
        $document = DB::collection('document_' . $project_key)
            ->where('_id', $id)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11902);
        }

        if (isset($document['d']) && $document['d'] === 1)
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project'))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else
        {
            if (!$this->isPermissionAllowed($project_key, 'manage_project') && $document['uploader']['id'] !== $this->user->id)
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }

        DB::collection('document_' . $project_key)->where('_id', $id)->update([ 'del_flag' => 1 ]);

        if (isset($document['d']) && $document['d'] === 1)
        {
            DB::collection('document_' . $project_key)->whereRaw([ 'pt' => $id ])->update([ 'del_flag' => 1 ]);
        }

        return Response()->json(['ecode' => 0, 'data' => [ 'id' => $id ]]);
    }

    /**
     * Upload file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $directory
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request, $project_key, $directory)
    {
        set_time_limit(0);

        if (!is_writable(config('filesystems.disks.local.root', '/tmp')))
        {
            throw new \UnexpectedValueException('the user has not the writable permission to the directory.', -15103);
        }

        if ($directory !== '0')
        {
            $isExists = DB::collection('document_' . $project_key)
                ->where('_id', $directory)
                ->where('d', 1)
                ->where('del_flag', '<>', 1)
                ->exists();
            if (!$isExists)
            {
                throw new \UnexpectedValueException('the parent directory does not exist.', -11905);
            }
        }

        $fields = array_keys($_FILES);
        $field = array_pop($fields);
        if (empty($_FILES) || $_FILES[$field]['error'] > 0)
        {
            throw new \UnexpectedValueException('upload file errors.', -11903);
        }

        $basename = md5(microtime() . $_FILES[$field]['name']);
        $sub_save_path = config('filesystems.disks.local.root', '/tmp') . '/' . substr($basename, 0, 2) . '/';
        if (!is_dir($sub_save_path))
        {
            @mkdir($sub_save_path);
        }
        move_uploaded_file($_FILES[$field]['tmp_name'], $sub_save_path . $basename);

        $data = [];

        $fname = $_FILES[$field]['name'];
        $extname = '';
        $segments = explode('.', $fname);
        if (count($segments) > 1)
        {
            $extname = '.' . array_pop($segments);
            $fname = implode('.', $segments);
        }
        $i = 1;
        while(true)
        {
            $isExists = DB::collection('document_' . $project_key)
                ->where('parent', $directory)
                ->where('name', $fname . ($i < 2 ? '' : ('(' . $i . ')')) . $extname)
                ->where('d', '<>', 1)
                ->where('del_flag', '<>', 1)
                ->exists();
            if (!$isExists)
            {
                break;
            }
            $i++; 
        }
        $data['name'] = $fname . ($i < 2 ? '' : ('(' . $i . ')')) . $extname;

        $data['pt']      = $this->getParentTree($project_key, $directory);
        $data['parent']  = $directory;
        $data['size']    = $_FILES[$field]['size'];
        $data['type']    = $_FILES[$field]['type'];
        $data['index']   = $basename;

        $data['uploader'] = [ 'id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email ];
        $data['uploaded_at'] = time();

        $id = DB::collection('document_' . $project_key)->insertGetId($data);
        $document = DB::collection('document_' . $project_key)->where('_id', $id)->first();

        return Response()->json(['ecode' => 0, 'data' => parent::arrange($document)]);
    }

    /**
     * Download file or directory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @param  String  $id
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, $project_key, $id)
    {
        set_time_limit(0);

        $document = DB::collection('document_' . $project_key)
            ->where('_id', $id)
            ->first();
        if (!$document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11902);
        }

        if (isset($document['d']) && $document['d'] === 1)
        {
            $this->downloadFolder($project_key, $document['name'], $id);
        }
        else
        {
            $this->downloadFile($document['name'], $document['index']);
        }
    }

    /**
     * Download file.
     *
     * @param  String  $name
     * @param  String  $directory
     * @return \Illuminate\Http\Response
     */
    public function downloadFolder($project_key, $name, $directory)
    {
        setlocale(LC_ALL, 'zh_CN.UTF-8'); 

        $basepath = '/tmp/' . md5($this->user->id . microtime());
        @mkdir($basepath);

        $this->contructFolder($project_key, $basepath . '/' . $name, $directory);

        $filename = $basepath . '/' . $name . '.zip';

        Zipper::make($filename)->folder($name)->add($basepath . '/' . $name);
        Zipper::close();

        File::download($filename, $name . '.zip');

        exec('rm -rf ' . $basepath);
    }

    /**
     * contruct file folder.
     *
     * @param  String  $fullpath
     * @param  String  $id
     * @return void
     */
    public function contructFolder($project_key, $fullpath, $id)
    {
        @mkdir($fullpath);

        $documents = DB::collection('document_' . $project_key)
            ->where('parent', $id)
            ->where('del_flag', '<>', 1)
            ->get();
        foreach ($documents as $doc)
        {
            if (isset($doc['d']) && $doc['d'] === 1)
            {
                $this->contructFolder($project_key, $fullpath . '/' . $doc['name'], $doc['_id']->__toString());
            }
            else
            {
                $filepath = config('filesystems.disks.local.root', '/tmp') . '/' . substr($doc['index'], 0, 2);
                $filename = $filepath . '/' . $doc['index'];
                if (file_exists($filename))
                {
                    @copy($filename, $fullpath . '/' . $doc['name']);
                }
            }
        }
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
            throw new \UnexpectedValueException('file does not exist.', -11904);
        }

        File::download($filename, $name);
    }
}
