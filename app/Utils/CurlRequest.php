<?php

namespace App\Utils;

class CurlRequest {

    /**
     * The curl get request.
     *
     * @param string $url
     * @param array $header
     * @param int $await
     * @return array
     */
    public static function get($url, $header=[], $await=5)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        
        if (!$header)
        {
            $header = [ 'Content-Type: application/json', 'Expect:' ];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $await);
        curl_setopt($ch, CURLOPT_TIMEOUT, $await);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $res = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($res, true) ?: [];
    }

    /**
     * The curl post request.
     *
     * @param string $url
     * @param array $header
     * @param array $data
     * @param int $await
     * @return array
     */
    public static function post($url, $header=[], $data=[], $await=5)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        if (!$header)
        {
            $header = [ 'Content-Type: application/json', 'Expect:' ];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $await);
        curl_setopt($ch, CURLOPT_TIMEOUT, $await);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true) ?: [];
    }
}
