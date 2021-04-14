<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class File extends Model
{
    //
    protected $table = 'file';

    protected $fillable = array(
        'name',
        'type',
        'size',
        'uploader',
        'index',
        'thumbnails_index',
        'del_flg',
        'project_key',//项目标记
        'download_flg', //下载标记 0开始 1成功 -1 失败
        'remote', //远程地址
        'tag', //标签
        'ext', //后缀
        'metas' //其他jsonmeta
    );

    static function isRemote($file){
        if(!$file) return false;
        if(isset($file->remote) && $file->remote && isset($file->download_flg) && $file->download_flg!=1){
            return true;
        }
        return false;

    }
}
