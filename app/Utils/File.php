<?php

namespace App\Utils;

class File {

    static function download($filename, $displayname)
    {
        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Accept-Length:" . filesize($filename));
        header("Content-Disposition: attachment; filename=" . $displayname);

        $fp = fopen($filename, 'rb');
        ob_end_clean();
        ob_start();
        while(!feof($fp))
        {
            $chunk_size = 1024 * 8;
            echo fread($fp, $chunk_size);
            ob_flush();
        }
        fclose($fp);
        ob_end_clean();
    }
}
