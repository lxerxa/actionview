<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use App\Events\FileUploadEvent;
use App\Events\FileDelEvent;
use App\Project\Eloquent\File;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Storage;
use QL\QueryList;
use League\HTMLToMarkdown\HtmlConverter;
use lyhiving\quickio\quickio;


class Files extends Model
{
    static $TBDOT = '_thumb.jpg';
    static $IMGURL = '{IMGURL}';
    static $project_key;


    static function extMime($ext)
    {
        $extensions = array(
            "ai" => "application/postscript",
            "aif" => "audio/x-aiff",
            "aifc" => "audio/x-aiff",
            "aiff" => "audio/x-aiff",
            "asc" => "text/plain",
            "au" => "audio/basic",
            "avi" => "video/x-msvideo",
            "bcpio" => "application/x-bcpio",
            "bin" => "application/octet-stream",
            "c" => "text/plain",
            "cc" => "text/plain",
            "ccad" => "application/clariscad",
            "cdf" => "application/x-netcdf",
            "class" => "application/octet-stream",
            "cpio" => "application/x-cpio",
            "cpt" => "application/mac-compactpro",
            "csh" => "application/x-csh",
            "css" => "text/css",
            "dir" => "application/x-director",
            "dms" => "application/octet-stream",
            "doc" => "application/msword",
            "drw" => "application/drafting",
            "dvi" => "application/x-dvi",
            "dwg" => "application/acad",
            "dxf" => "application/dxf",
            "dxr" => "application/x-director",
            "eps" => "application/postscript",
            "etx" => "text/x-setext",
            "exe" => "application/octet-stream",
            "ez" => "application/andrew-inset",
            "f" => "text/plain",
            "f90" => "text/plain",
            "fli" => "video/x-fli",
            "gif" => "image/gif",
            "gtar" => "application/x-gtar",
            "gz" => "application/x-gzip",
            "h" => "text/plain",
            "hdf" => "application/x-hdf",
            "hh" => "text/plain",
            "hqx" => "application/mac-binhex40",
            "htm" => "text/html",
            "html" => "text/html",
            "ice" => "x-conference/x-cooltalk",
            "ief" => "image/ief",
            "iges" => "model/iges",
            "igs" => "model/iges",
            "ips" => "application/x-ipscript",
            "ipx" => "application/x-ipix",
            "jpe" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "jpg" => "image/jpeg",
            "js" => "application/x-javascript",
            "kar" => "audio/midi",
            "latex" => "application/x-latex",
            "lha" => "application/octet-stream",
            "lsp" => "application/x-lisp",
            "lzh" => "application/octet-stream",
            "m" => "text/plain",
            "man" => "application/x-troff-man",
            "me" => "application/x-troff-me",
            "mesh" => "model/mesh",
            "mid" => "audio/midi",
            "midi" => "audio/midi",
            "mif" => "application/vnd.mif",
            "mime" => "www/mime",
            "mov" => "video/quicktime",
            "movie" => "video/x-sgi-movie",
            "mp2" => "audio/mpeg",
            "mp3" => "audio/mpeg",
            "mpe" => "video/mpeg",
            "mpeg" => "video/mpeg",
            "mpg" => "video/mpeg",
            "mpga" => "audio/mpeg",
            "ms" => "application/x-troff-ms",
            "msh" => "model/mesh",
            "nc" => "application/x-netcdf",
            "oda" => "application/oda",
            "pbm" => "image/x-portable-bitmap",
            "pdb" => "chemical/x-pdb",
            "pdf" => "application/pdf",
            "pgm" => "image/x-portable-graymap",
            "pgn" => "application/x-chess-pgn",
            "php" => "text/plain",
            "png" => "image/png",
            "pnm" => "image/x-portable-anymap",
            "pot" => "application/mspowerpoint",
            "ppm" => "image/x-portable-pixmap",
            "pps" => "application/mspowerpoint",
            "ppt" => "application/mspowerpoint",
            "ppz" => "application/mspowerpoint",
            "pre" => "application/x-freelance",
            "prt" => "application/pro_eng",
            "ps" => "application/postscript",
            "py" => "text/plain",
            "qt" => "video/quicktime",
            "ra" => "audio/x-realaudio",
            "ram" => "audio/x-pn-realaudio",
            "ras" => "image/cmu-raster",
            "rgb" => "image/x-rgb",
            "rm" => "audio/x-pn-realaudio",
            "roff" => "application/x-troff",
            "rpm" => "audio/x-pn-realaudio-plugin",
            "rtf" => "text/rtf",
            "rtx" => "text/richtext",
            "scm" => "application/x-lotusscreencam",
            "set" => "application/set",
            "sgm" => "text/sgml",
            "sgml" => "text/sgml",
            "sh" => "application/x-sh",
            "shar" => "application/x-shar",
            "silo" => "model/mesh",
            "sit" => "application/x-stuffit",
            "skd" => "application/x-koan",
            "skm" => "application/x-koan",
            "skp" => "application/x-koan",
            "skt" => "application/x-koan",
            "smi" => "application/smil",
            "smil" => "application/smil",
            "snd" => "audio/basic",
            "sol" => "application/solids",
            "spl" => "application/x-futuresplash",
            "src" => "application/x-wais-source",
            "step" => "application/STEP",
            "stl" => "application/SLA",
            "stp" => "application/STEP",
            "sv4cpio" => "application/x-sv4cpio",
            "sv4crc" => "application/x-sv4crc",
            "swf" => "application/x-shockwave-flash",
            "t" => "application/x-troff",
            "tar" => "application/x-tar",
            "tcl" => "application/x-tcl",
            "tex" => "application/x-tex",
            "texi" => "application/x-texinfo",
            "texinfo" => "application/x-texinfo",
            "tif" => "image/tiff",
            "tiff" => "image/tiff",
            "tr" => "application/x-troff",
            "tsi" => "audio/TSP-audio",
            "tsp" => "application/dsptype",
            "tsv" => "text/tab-separated-values",
            "txt" => "text/plain",
            "unv" => "application/i-deas",
            "ustar" => "application/x-ustar",
            "vcd" => "application/x-cdlink",
            "vda" => "application/vda",
            "viv" => "video/vnd.vivo",
            "vivo" => "video/vnd.vivo",
            "vrml" => "model/vrml",
            "wav" => "audio/x-wav",
            "webp" => "image/webp",
            "wrl" => "model/vrml",
            "xbm" => "image/x-xbitmap",
            "xlc" => "application/vnd.ms-excel",
            "xll" => "application/vnd.ms-excel",
            "xlm" => "application/vnd.ms-excel",
            "xls" => "application/vnd.ms-excel",
            "xlw" => "application/vnd.ms-excel",
            "xml" => "text/xml",
            "xpm" => "image/x-xpixmap",
            "xwd" => "image/x-xwindowdump",
            "xyz" => "chemical/x-pdb",
            "zip" => "application/zip"

        );
        $ext = strtolower($ext);
        $mime_type = $extensions[$ext];
        return $mime_type;
    }

    static function disk($type = null)
    {
        if (is_null($type)) $type = self::defaultDisk();
        return Storage::disk($type);
    }

    static function setProjectKey($project_key)
    {
        self::$project_key = $project_key;
    }

    static function dayPath($format = 'Ym')
    {
        return date($format) . '/';
    }

    static function subPath($format = 'Ym')
    {
        $path = self::localPath() . '/' . self::dayPath($format);
        return $path;
    }

    static function tmpDir()
    {
        return sys_get_temp_dir();
    }

    /**
     * 获取绝对路径
     * auto 为真时自动创建父级目录
     */
    static function absPath($file = null, $auto = false)
    {
        $path = self::localPath() . '/' . $file;
        if ($auto) {
            self::checkParent($file);
        }
        return $path;
    }

    static function checkParent($path)
    {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        return $path;
    }

    //上传处理
    static function saveTo($file, $filename)
    {
        $ret = $file->storeAs(dirname($filename),basename($filename), self::defaultDisk());
        @unlink($file->getPathname());
        return $ret;
    }

    static function isUrl($filename)
    {
        if ($filename && (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0 || strpos($filename, 'ftp://') === 0)) {
            return true;
        }
        return false;
    }

    static function getExt($filename)
    {
        if (self::isUrl($filename)) {
            $path = parse_url($filename, PHP_URL_PATH);
            $ext =  pathinfo($path, PATHINFO_EXTENSION);
        } else {
            $ext =  pathinfo($filename, PATHINFO_EXTENSION);
        }
        return strtolower($ext);
    }

    static function baseName($filename, $dot = null, $abs = false)
    {
        if (!$abs) {
            $ext = self::getExt($filename);

            if (self::isUrl($filename)) {
                $filecode = md5($filename);
            } else {
                $filecode = md5(microtime() . $filename);
            }
            $basename = date('d_').substr($filecode, 8, 16);
            // dump([self::isUrl($filename), $filename, $filecode, $basename]);
            if (self::saftExt($ext)) {
                $basename .= '.' . $ext;
            }
        } else {
            $basename = $filename;
        }
        if ($dot) $basename .= $dot;
        return $basename;
    }

    static function basePath($filename, $dot = null,  $format = 'Ym')
    {
        $basename = self::baseName($filename, $dot);
        return (self::$project_key ? self::$project_key . "/" : "") . self::dayPath($format) . $basename;
    }

    static function saftExt($ext)
    {
        $ext = strtolower($ext);
        if (!in_array($ext, ['php', 'asp', 'jsp', 'exe', 'cgi', 'html', 'htm', 'jhtml', 'shtml'])) {
            return true;
        }
        return false;
    }


    static function isImg($ext)
    {
        if (strpos($ext, '.') !== false) {
            $ext = self::getExt($ext);
        }
        $ext = strtolower($ext);
        if (in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'html', 'webp'])) {
            return true;
        }
        return false;
    }


    static function makeThumbIndex($filename, $basename, $thumbnail_size=200)
    {
        $ext = self::getExt($filename);
        if(!self::isImg($ext)) return false;
        if(self::defaultDisk()=='local'){
            self::makeThumb($filename, $thumbnail_size);
        }
        return self::baseName($basename, self::$TBDOT, true);
    }

    /**
     * 去掉URL的?
     */
    static function pathUrl($url)
    {
        if (strpos($url, '?') == false) return $url;
        $path = parse_url($url);
        return $path['scheme'] . '://' . $path['host'] . (isset($path['port']) && $path['port'] ? ":" . $path['port'] : "") . $path['path'];
    }


    static function getUrl($url, $meta = [])
    {
        $client = new Client();
        if (!$meta) {
            $meta = [
                "Host" => parse_url($url, PHP_URL_HOST),
                "User-Agent" => "Mozilla/5.0 (iPhone; CPU iPhone OS 14_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x18000238) NetType/WIFI Language/zh_CN",
                "Referer" => $url,
                "Cookie" => "",
                "Accept-Encoding" => "gzip, zlib, deflate, zstd, br"
            ];
        }
        $request = new Request(
            "GET",
            $url,
            $meta
        );

        $response = $client->send($request);
        $body = $response->getBody();
        $content = $body->getContents();
        if (!$content) return false;
        return $content;
    }

    static function localPath()
    {
        $saveway = self::defaultDisk();
        if ($saveway == 'local') {
            return config('filesystems.disks.local.root', '/tmp');
        } else {
            return '';
        }
    }

    static function defaultDisk()
    {
        $saveway = config('filesystems.cloud', config('filesystems.default'));
        return $saveway;
    }


    static function local()
    {
        return Storage::disk('local');
    }

    static function rmdir($dir)
    {
        return quickio::rmdir($dir);
    }


    static function makeThumb($filename, $thumbnail_size = 200)
    {
        if (!$filename || !is_file($filename)) return false;
        $ext = self::getExt($filename);
        if (!self::isImg($ext)) return false;
        $size = @getimagesize($filename);
        if (!$size) return false;
        $width = $size[0];
        $height = $size[1];
        $scale = $width < $height ? $height : $width;
        $thumbnails_width = floor($thumbnail_size * $width / $scale);
        $thumbnails_height = floor($thumbnail_size * $height / $scale);
        $thumbnails_filename = self::baseName($filename, self::$TBDOT, true);
        self::checkParent($thumbnails_filename);
        if ($scale <= $thumbnail_size) {
            @copy($filename, $thumbnails_filename);
        } else if ($ext == 'jpeg' || $ext == 'jpg') {
            $src_image = @imagecreatefromjpeg($filename);
            $dst_image = @imagecreatetruecolor($thumbnails_width, $thumbnails_height);
            @imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
            @imagejpeg($dst_image, $thumbnails_filename);
        } else if ($ext == 'png') {
            $src_image = @imagecreatefrompng($filename);
            $dst_image = @imagecreatetruecolor($thumbnails_width, $thumbnails_height);
            @imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
            @imagepng($dst_image, $thumbnails_filename);
        } else if ($ext == 'gif') {
            $src_image = @imagecreatefromgif($filename);
            $dst_image = @imagecreatetruecolor($thumbnails_width, $thumbnails_height);
            @imagecopyresized($dst_image, $src_image, 0, 0, 0, 0, $thumbnails_width, $thumbnails_height, $width, $height);
            @imagegif($dst_image, $thumbnails_filename);
        } else {
            @copy($filename, $thumbnails_filename);
        }
        return $thumbnails_filename;
    }

    static function downloadCreate($url, $project_key, $issue_id = null, $field = null, $tag = 'downloader', $userinfo = [])
    {
        $url = trim($url);
        if (!$url) return false;
        if (!self::isUrl($url)) return false;
        if (!self::$project_key) return false;
        self::setProjectKey($project_key);
        $file = File::where('project_key', $project_key)
            ->where('tag', $tag)
            ->where('remote', $url)
            // ->where('del_flg', '<>', 1) //一个站点一个标签对应只能下载一次一个地址
            ->first();
        if ($file) return $file;
        $urla = self::pathUrl($url);
        $path = parse_url($urla);
        $data = [];
        $data['name']    = basename($path['path']);
        $data['size']    = 0;
        $data['index']   = self::basePath($url);
        $data['ext'] = self::getExt($url);
        if (self::isImg($data['ext'])) {
            $data['thumbnails_index']   = self::basePath($url, self::$TBDOT);
        }
        $data['type']    = self::extMime($data['ext']);
        $data['tag'] = $tag;
        $data['download_flg'] = 0; //未开始
        $data['remote'] = $url;
        $data['project_key']    = $project_key;
        $data['uploader'] = $uploader =  $userinfo ? $userinfo : ['id' => 0, 'name' => 'Downloader', 'email' => 'downloader@tedx.net'];
        $file = File::create($data);
        if ($issue_id && $field) {
            Event::dispatch(new FileUploadEvent($project_key, $issue_id, $field, $file->id, $uploader));
        }
        return $file;
    }

    static function download($id)
    {
        if (!$id) return false;
        $file = File::find($id);
        if (!$file) return false;
        if (!File::isRemote($file)) return $file;
        if (!$file->index) return false;
        if (isset($file->download_flg) && $file->download_flg == 1) {
            return $file;
        }
        $saveway = self::defaultDisk();
        $path = self::absPath($file->index, $saveway == 'local');
        $content = self::getUrl($file->remote);
        if (!$content) {
            $file->download_flg = -1; //标记
            $file->save();
            return false;
        }
        $downloaded = Storage::disk($saveway)->put($file->index, $content);
        if (isset($file->thumbnails_index) && $file->thumbnails_index && $saveway == 'local') { //本地就生缩略图
            self::makeThumb($path);
        }
        $file->download_flg = $downloaded ? 1 : -1; //标记下载标记
        if ($downloaded) {
            $file->size = strlen($content);
        }
        $file->save();
        return $file;
    }

    /**
     * 替换相关文档
     */
    static function htmlReplace($html, $project_key, $issue_id = null, $field = null, $tag = 'downloader', $userinfo = [])
    {
        $data = QueryList::html($html)->rules([
            'image' => ['img', 'src']
        ])->query()->getData(function ($item) {
            return $item;
        });
        $items = $data->all();
        if (!$items) return $html;
        $orig = [];
        $replace = [];
        foreach ($items as $r) {
            if (!isset($r['image']) || !$r['image']) continue;
            $file = self::downloadCreate($r['image'], $project_key, $issue_id, $field, $tag, $userinfo);
            if (!$file) continue;
            $replace[] = self::$IMGURL . '/' . $file->index;
            $orig[] =  $r['image'];
        }
        if ($replace) {
            $html = str_replace($orig, $replace, $html);
        }
        return $html;
    }

    static function html2md($html)
    {
        $converter = new HtmlConverter();
        $markdown = $converter->convert($html);
        return $markdown;
    }
}
