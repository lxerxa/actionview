<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Http\Controllers\Controller;
use App\Project\Eloquent\File;
use App\Events\FileUploadEvent;
use App\Events\FileDelEvent;
use App\Utils\File as FileUtil;
use App\Models\Files as FilesModel;
use Illuminate\Support\Facades\Storage;


use DB;
use Illuminate\Cache\FileStore;

class FileController extends Controller
{
    /**
     * Upload file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String  $project_key
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request, $project_key)
    {
        set_time_limit(0);
        // if (!is_writable(config('filesystems.disks.local.root', '/tmp'))) {
        //     throw new \UnexpectedValueException('the user has not the writable permission to the directory.', -15103);
        // }
        if (empty($_FILES)) {
            throw new \UnexpectedValueException('upload file errors.', -15101);
        }

        $thumbnail_size = 200;
        $uploaded_files = [];
        FilesModel::setProjectKey($project_key);
        $thumb_dot = FilesModel::$TBDOT;

        foreach ($_FILES as $field => $tmpfile) {
            if ($tmpfile['error'] > 0) {
                continue;
            }

            $file = $request->file($field);
            if (!$file->isValid()) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if(!$content){
                continue;
            }

            $ext =  $file->guessExtension();
            $basename = FilesModel::basePath($file->getClientOriginalName());
            $sub_save_path = FilesModel::absPath(null);
            $filename = $sub_save_path . $basename;
            $data = [];
            $data['name']    = $file->getClientOriginalName();
            $data['size']    = $file->getSize();
            $data['type']    = $file->getClientMimeType();
            $data['index']   = $basename;
            $uploaded = FilesModel::saveTo($file, $filename);
            if(!$uploaded){
                continue;
            }
            $thumbmail_index = FilesModel::makeThumbIndex($filename,$basename,$thumbnail_size );
            if($thumbmail_index){
                $data['thumbnails_index'] = $thumbmail_index;
            }
            
            $data['ext'] = $ext;
            $data['tag'] = 'uploader';
            $data['project_key']    = $project_key;
            $data['uploader'] = ['id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email];
            $file = File::create($data);
            $uploaded_files[] = $file;
            $issue_id = $request->input('issue_id');
            if (isset($issue_id) && $issue_id) {
                Event::dispatch(new FileUploadEvent($project_key, $issue_id, $field, $file->id, $data['uploader']));
            }
        }

        if (count($uploaded_files) > 1) {
            $data = [];
            foreach ($uploaded_files as $file) {
                $data[] = ['file' => $file, 'filename' => '/actionview/api/project/' . $project_key . '/file/' . $file->id];
            }
            return Response()->json(['ecode' => 0, 'data' => $data]);
        } else {
            $file = array_pop($uploaded_files);
            $data = ['field' => $field, 'file' => $file, 'filename' => '/actionview/api/project/' . $project_key . '/file/' . $file->id];
            return Response()->json(['ecode' => 0, 'data' => $data]);
        }
    }

    /**
     * Download small image file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String $id
     */
    public function downloadThumbnail(Request $request, $project_key, $id)
    {
        $file = File::find($id);
        if (!$file) {
            throw new \UnexpectedValueException('id does not exist.', -15100);
        }
        if (!File::isRemote($file)) {
            if(FilesModel::defaultDisk()=='local'){
                $filename = FilesModel::absPath($file->thumbnails_index);
                if (!file_exists($filename)) {
                    throw new \UnexpectedValueException('file does not exist.', -15100);
                }
                FileUtil::download($filename, $file->name);
            }else{
                $url = FilesModel::disk()->getUrl($file->index.FilesModel::$TBDOT);
                return redirect($url);
            }

        } else {
            return redirect($file->remote);
        }
    }

    /**
     * Download file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String $id
     */
    public function download(Request $request, $project_key, $id)
    {
        set_time_limit(0);

        $file = File::find($id);
        if (!$file || $file->del_flg == 1) {
            throw new \UnexpectedValueException('file does not exist.', -15100);
        }

        if (!File::isRemote($file)) {
            if(FilesModel::defaultDisk()=='local'){
                $filename = FilesModel::absPath($file->index);
                if (!file_exists($filename)) {
                    throw new \UnexpectedValueException('file does not exist.', -15100);
                }

                if ($file->type == 'application/pdf') {
                    FileUtil::pdfPreview($filename, $file->name);
                } else {
                    FileUtil::download($filename, $file->name);
                }
            }else{
                $url = FilesModel::disk()->getUrl($file->index);
                return redirect($url);
            }
        } else {
            return redirect($file->remote);
        }
    }



    /**
     * get avatar file.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function getAvatar(Request $request)
    {
        $fid = $request->input('fid');
        if (!isset($fid) || !$fid) {
            throw new \UnexpectedValueException('the avatar file id cannot empty.', -15100);
        }

        $filename = FilesModel::absPath(null) . '/avatar/' . $fid;
        if(FilesModel::defaultDisk()=='local'){
            if (!file_exists($filename)) {
                throw new \UnexpectedValueException('the avatar file does not exist.', -15100);
            }

            FileUtil::download($filename, 'avatar_' . basename($filename) . '.png');
        }else{
            $url = FilesModel::disk()->getUrl($filename);
            return redirect($url);
        }
    }

    /**
     * Delete file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String $project_key
     * @param  String $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, $project_key, $id)
    {
        $file = File::find($id);
        //if (!file || $file->del_flg == 1)
        //{
        //    throw new \UnexpectedValueException('file does not exist.', -15100);
        //}

        if ($file && !$this->isPermissionAllowed($project_key, 'remove_file') && !($this->isPermissionAllowed($project_key, 'remove_self_file') && $file->uploader['id'] == $this->user->id)) {
            return Response()->json(['ecode' => -10002, 'emsg' => 'permission denied.']);
        }

        $issue_id = $request->input('issue_id');
        $field_key = $request->input('field_key');
        if (isset($issue_id) && $issue_id && isset($field_key) && $field_key) {
            $user = ['id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email];
            Event::dispatch(new FileDelEvent($project_key, $issue_id, $field_key, $id, $user));
        }

        // logically deleted
        if ($file) {
            $file->fill(['del_flg' => 1])->save();
        }

        $issue = DB::collection('issue_' . $project_key)->where('_id', $issue_id)->first();
        if (array_search($id, $issue[$field_key]) === false) {
            return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
        } else {
            throw new \UnexpectedValueException('file deletion failed.', -15102);
        }
    }

    /**
     * Upload temporary file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadTmpFile(Request $request)
    {
        set_time_limit(0);

        if (empty($_FILES) || $_FILES['file']['error'] > 0) {
            throw new \UnexpectedValueException('upload file errors.', -15101);
        }

        $basename = FilesModel::basePath($_FILES['file']['name']);
        $filename = FilesModel::tmpDir() . '/' . $basename;
        move_uploaded_file($_FILES['file']['tmp_name'], FilesModel::checkParent($filename));
        // move original file
        @rename($filename, FilesModel::checkParent(FilesModel::absPath($basename)));
        $data['uploader'] = ['id' => $this->user->id, 'name' => $this->user->first_name, 'email' => $this->user->email];
        $file = File::create($data);

        return Response()->json(['ecode' => 0, 'data' => ['fid' => $basename, 'fname' => $_FILES['file']['name']]]);
    }
}
