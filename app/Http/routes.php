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

// session router
Route::post('api/session', 'SessionController@create');
Route::get('api/session', 'SessionController@getSess');
Route::delete('api/session', 'SessionController@destroy');

Route::post('api/user/register', 'UserController@register');

Route::get('user/{id}/resetpwd', 'UserController@showResetpwd'); //fix me
Route::post('user/{id}/resetpwd', 'UserController@doResetpwd'); // fix me

Route::get('api/addadmin/{id}', 'SyssettingController@addAdmin'); // delete me

Route::group([ 'middleware' => 'can' ], function () {
    // project route
    Route::get('api/myproject', 'ProjectController@myproject');
    Route::get('api/project/recent', 'ProjectController@recent');
    Route::get('api/project', 'ProjectController@index');
    Route::get('api/project/checkkey/{key}', 'ProjectController@checkKey');
    Route::get('api/project/options', 'ProjectController@getOptions');
    Route::get('api/project/{key}', 'ProjectController@show');
    Route::post('api/project', 'ProjectController@store');
    Route::put('api/project/{id}', 'ProjectController@update');
    Route::get('api/project/{id}/createindex', 'ProjectController@createIndex');
    Route::post('api/project/batch/status', 'ProjectController@updMultiStatus');
    Route::post('api/project/batch/createindex', 'ProjectController@createMultiIndex');
    Route::delete('api/project/{id}', 'ProjectController@destroy');

    Route::get('api/user/search', 'UserController@search');
    Route::get('api/user/{id}/renewpwd', 'UserController@renewPwd');
    Route::post('api/user/batch/delete', 'UserController@delMultiUser');
    Route::post('api/user/fileupload', 'UserController@upload');
    Route::post('api/user/imports', 'UserController@imports');
    Route::resource('api/user', 'UserController');

    Route::get('api/group/search', 'GroupController@search');
    Route::post('api/group/batch/delete', 'GroupController@delMultiGroups');
    Route::resource('api/group', 'GroupController');

    Route::get('api/mysetting', 'MysettingController@show');
    Route::post('api/mysetting/account', 'MysettingController@updAccounts');
    Route::post('api/mysetting/resetpwd', 'MysettingController@resetPwd');
    Route::post('api/mysetting/notify', 'MysettingController@setNotifications');
    Route::post('api/mysetting/favorite', 'MysettingController@setFavorites');

    // middleware is put into controller
    Route::get('api/syssetting', 'SyssettingController@show');
    Route::post('api/syssetting', 'SyssettingController@update');
    Route::post('api/syssetting/restpwd', 'SyssettingController@resetPwd');
    Route::post('api/syssetting/sendtestmail', 'SyssettingController@sendTestMail');

    Route::get('api/getavatar', 'FileController@getAvatar');
    Route::get('api/setavatar', 'MysettingController@setAvatar');
});

// project config
Route::group([ 'prefix' => 'api/project/{project_key}', 'middleware' => [ 'can', 'privilege:manage_project' ] ], function () {
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
    Route::post('role/{id}/actor', 'RoleController@setActor');
    Route::post('role/{id}/groupactor', 'RoleController@setGroupActor');
    Route::resource('role', 'RoleController');
    // project event config
    Route::resource('events', 'EventsController');
    Route::get('events/{event_id}/reset', 'EventsController@reset');
    // project priority config
    Route::resource('priority', 'PriorityController');
    Route::post('priority/batch', 'PriorityController@handle');
    // project state config
    Route::resource('state', 'StateController');
    // project resolution config
    Route::resource('resolution', 'ResolutionController');
    Route::post('resolution/batch', 'ResolutionController@handle');
    // Route::resource('module', 'ModuleController', [ 'only' => [ 'store', 'update', 'destory' ] ]);
    // Route::resource('version', 'VersionController', [ 'only' => [ 'store', 'update', 'destory' ] ]);
});

Route::group([ 'prefix' => 'api/project/{project_key}', 'middleware' => [ 'can', 'privilege:view_project' ] ], function () {
    // project summary 
    Route::get('summary', 'SummaryController@index');
    // config summary 
    Route::get('config', 'ConfigController@index');
    // project activity
    Route::get('activity', 'ActivityController@index');
    // project module config
    Route::resource('module', 'ModuleController');
    // project version config
    Route::resource('version', 'VersionController');
    // project team
    Route::get('team', 'RoleController@index');
    // preview the workflow chart 
    Route::get('workflow/{id}/preview', 'WorkflowController@preview');

    Route::get('issue/searcher', 'IssueController@getSearchers');
    Route::post('issue/searcher', 'IssueController@addSearcher');
    Route::post('issue/searcher/batch', 'IssueController@handleSearcher');
    Route::get('issue/options', 'IssueController@getOptions');
    Route::get('issue/search', 'IssueController@search');

    Route::get('issue/{id}', 'IssueController@show');
    Route::get('issue', 'IssueController@index');
    Route::post('issue', [ 'middleware' => 'privilege:create_issue', 'uses' => 'IssueController@store' ]);
    // this middleware is put into action
    Route::put('issue/{id}', 'IssueController@update');
    Route::delete('issue/{id}', [ 'middleware' => 'privilege:delete_issue', 'uses' => 'IssueController@destroy' ]);

    Route::get('issue/{id}/history', 'IssueController@getHistory');
    Route::post('issue/{id}/watching', 'IssueController@watch');

    Route::post('issue/{id}/workflow/{workflow_id}/action/{action_id}', [ 'middleware' => 'privilege:exec_workflow', 'uses' => 'IssueController@doAction' ]);
    Route::post('issue/{id}/assign', [ 'uses' => 'IssueController@setAssignee' ]); // this middleware is put into the action
    Route::post('issue/{id}/move', [ 'middleware' => 'privilege:move_issue', 'uses' => 'IssueController@move' ]);
    Route::post('issue/copy', [ 'middleware' => 'privilege:create_issue', 'uses' => 'IssueController@copy' ]);
    Route::post('issue/{id}/convert', [ 'middleware' => 'privilege:edit_issue', 'uses' => 'IssueController@convert' ]);
    Route::get('issue/{id}/reset', [ 'middleware' => 'privilege:reset_issue', 'uses' => 'IssueController@resetState' ]);

    Route::resource('issue/{id}/comments', 'CommentsController');
    Route::resource('issue/{id}/worklog', 'WorklogController');

    Route::post('link', 'LinkController@store');
    Route::delete('link/{id}', 'LinkController@destroy');

    Route::post('file', [ 'middleware' => 'privilege:upload_file', 'uses' => 'FileController@upload' ]);
    Route::get('file/{id}', [ 'middleware' => 'privilege:view_project', 'uses' => 'FileController@download' ]);
    Route::delete('file/{id}', [ 'middleware' => 'privilege:remove_file', 'uses' => 'FileController@delete' ]);
});
