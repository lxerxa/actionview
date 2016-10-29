<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/phpinfo', function () {
   $src_image = imagecreatefrompng('/tmp/bb');
   $dst_image = imagecreatetruecolor(100,41);
   imagecopyresized($dst_image,$src_image,0,0,0,0,100,41,200,43);
   imagejpeg($dst_image,'/tmp/sbb');
   //var_dump(getimagesize('/tmp/bb'));
   exit();
});

Route::post('api/uploadfile', 'FileController@upload');

// session router
Route::post('api/session', 'SessionController@create');
Route::post('api/register', 'UserController@regist');

Route::group([ 'middleware' => 'can' ], function ()
{
    Route::resource('api/project', 'ProjectController');
    Route::resource('api/user', 'UserController');
    // create or update colletion indexes
    // Route::post('sysindexes', 'SysIndexController');
    // user logout
    Route::delete('api/session', 'SessionController@destroy');
});

// project config
Route::group([ 'prefix' => 'api/project/{project_key}', 'middleware' => [ 'can', 'privilege:project:admin_project' ] ], function ()
{
    // project type config
    Route::resource('type', 'TypeController');
    Route::post('type/batch', 'TypeController@handle');
    // project field config
    Route::resource('field', 'FieldController');
    // project screen config
    Route::resource('screen', 'ScreenController');
    // project workflow config
    Route::resource('workflow', 'WorkflowController');
    // project role config
    Route::resource('role', 'RoleController');
    // project priority config
    Route::resource('priority', 'PriorityController');
    Route::post('priority/batch', 'PriorityController@handle');
    // project state config
    Route::resource('state', 'StateController');
    // project resolution config
    Route::resource('resolution', 'ResolutionController');
    Route::post('resolution/batch', 'ResolutionController@handle');
    // project module config
    Route::resource('module', 'ModuleController');
    // project version config
    Route::resource('version', 'VersionController');
});

Route::group([ 'prefix' => 'api/project/{project_key}', 'middleware' => [ 'can' ] ], function ()
{
    Route::get('issue/searcher', 'IssueController@getSearchers');
    Route::post('issue/searcher', 'IssueController@addSearcher');
    Route::delete('issue/searcher/{id}', 'IssueController@delSearcher');
    Route::get('issue/options', 'IssueController@getOptions');
    Route::resource('issue', 'IssueController');
});
