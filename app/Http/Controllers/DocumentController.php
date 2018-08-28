<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Acl\Acl;
use DB;

use Zipper;

class DocumentController extends Controller
{

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
     * @param  string  $directory
     * @return \Illuminate\Http\Response
     */
    public function createFolder(Request $request, $project_key, $directory)
    {
        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11900);
        }
        $insValues['name'] = $name;

        $isExists = DB::collection('document_' . $project_key)
            ->where('parent', $directory)
            ->where('name', $name)
            ->where('d', 1)
            ->where('del_flag', '<>', 1)
            ->exists();
        if ($isExists)
        {
            throw new \UnexpectedValueException('the name cannot be repeated.', -11901);
        }

        $insValues['pt'] = $this->getParentTree($project_key, $directory);
        $insValues['parent'] = $directory;
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
        $name =  $request->input('name');
        if (!isset($name) || !$name)
        {
            throw new \UnexpectedValueException('the name can not be empty.', -11900);
        }

        $old_document = DB::collection('document_' . $project_key)
            ->where('_id', $id)
            ->first();
        if (!$old_document)
        {
            throw new \UnexpectedValueException('the object does not exist.', -11902);
        }

        if (isset($old_document['d']) && $old_document['d'] === 1)
        {
            if (!Acl::isAllowed($this->user->id, 'manage_project', $project_key)) 
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else
        {
            if (!Acl::isAllowed($this->user->id, 'upload_file', $project_key) || !Acl::isAllowed($this->user->id, 'download_file', $project_key)) 
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
            if (!Acl::isAllowed($this->user->id, 'manage_project', $project_key))
            {
                return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
            }
        }
        else
        {
            if (!Acl::isAllowed($this->user->id, 'remove_file', $project_key))
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
        $fields = array_keys($_FILES);
        $field = array_pop($fields);
        if ($_FILES[$field]['error'] > 0)
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
        $basepath = '/tmp/' . md5($this->user->id . microtime());
        @mkdir($basepath);

        $this->contructFolder($project_key, $basepath . '/' . $name, $directory);

        $filename = $basepath . '/' . $name . '.zip';

        Zipper::make($filename)->folder($name)->add($basepath . '/' . $name);
        Zipper::close();

        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Accept-Length:" . filesize($filename));
        header("Content-Disposition: attachment; filename=" . $name . '.zip');
        echo file_get_contents($filename);
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

        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Accept-Length:" . filesize($filename));
        header("Content-Disposition: attachment; filename=" . $name);
        echo file_get_contents($filename);
    }
}
