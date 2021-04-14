<?php
if (is_file(__DIR__.'/setting_local.php')) {
    return require __DIR__.'/setting_local.php';
}
return [

    'imgurl' => env('APP_URL', 'http://localhost'),
    'erpapi' => env('APP_URL', 'http://localhost'),

];
