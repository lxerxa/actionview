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
// webhook api
Route::post('api/webhook/{type}/project/{key}', 'WebhookController@exec');

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
    Route::post('api/user/batch/delete', 'UserController@delMultiUsers');
    Route::post('api/user/batch/invalidate', 'UserController@invalidateMultiUsers');
    Route::post('api/user/fileupload', 'UserController@upload');
    Route::post('api/user/imports', 'UserController@imports');
    Route::resource('api/user', 'UserController');

    Route::get('api/group/search', 'GroupController@search');
    Route::post('api/group/batch/delete', 'GroupController@delMultiGroups');
    Route::resource('api/group', 'GroupController');

    Route::get('api/directory/{id}/test', 'DirectoryController@test');
    Route::get('api/directory/{id}/sync', 'DirectoryController@sync');
    Route::resource('api/directory', 'DirectoryController');

    Route::get('api/mysetting', 'MysettingController@show');
    Route::post('api/mysetting/account', 'MysettingController@updAccounts');
    Route::post('api/mysetting/resetpwd', 'MysettingController@resetPwd');
    Route::post('api/mysetting/notify', 'MysettingController@setNotifications');
    Route::post('api/mysetting/favorite', 'MysettingController@setFavorites');
    Route::post('api/mysetting/avatar', 'MysettingController@setAvatar');

    // middleware is put into controller
    Route::get('api/syssetting', 'SyssettingController@show');
    Route::post('api/syssetting', 'SyssettingController@update');
    Route::post('api/syssetting/restpwd', 'SyssettingController@resetPwd');
    Route::post('api/syssetting/sendtestmail', 'SyssettingController@sendTestMail');

    Route::get('api/getavatar', 'FileController@getAvatar');
    Route::get('api/downloadusertpl', 'UserController@downloadUserTpl');
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
    Route::get('role/{id}/reset', 'RoleController@reset');
    Route::post('role/{id}/permissions', 'RoleController@setPermissions');
    Route::post('role/{id}/actor', 'RoleController@setActor');
    Route::post('role/{id}/groupactor', 'RoleController@setGroupActor');
    Route::resource('role', 'RoleController');
    // project event config
    Route::get('events/{event_id}/reset', 'EventsController@reset');
    Route::post('events/{event_id}/notify', 'EventsController@setNotify');
    Route::resource('events', 'EventsController');
    // project priority config
    Route::resource('priority', 'PriorityController');
    Route::post('priority/batch', 'PriorityController@handle');
    // project state config
    Route::resource('state', 'StateController');
    Route::post('state/batch', 'StateController@handle');
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
    Route::post('module/{id}/delete', 'ModuleController@delete');
    Route::resource('module', 'ModuleController');
    Route::post('module/batch', 'ModuleController@handle');
    // project version config
    Route::post('version/merge', 'VersionController@merge');
    Route::post('version/{id}/release', 'VersionController@release');
    Route::post('version/{id}/delete', 'VersionController@delete');
    Route::resource('version', 'VersionController');
    // project report 
    Route::get('report/index', 'ReportController@index');
    Route::get('report/trend', 'ReportController@getTrends');
    Route::get('report/worklog', 'ReportController@getWorklogs');
    Route::get('report/worklog/list', 'ReportController@getWorklogList');
    Route::get('report/worklog/issue/{issue_id}', 'ReportController@getWorklogDetail');
    Route::get('report/{mode}/filters/reset', 'ReportController@resetSomeFilters');
    Route::post('report/{mode}/filters', 'ReportController@editSomeFilters');
    Route::post('report/{mode}/filter', 'ReportController@saveFilter');
    // project team
    Route::get('team', 'RoleController@index');
    // preview the workflow chart 
    Route::get('workflow/{id}/preview', 'WorkflowController@preview');

    Route::get('issue/options', 'IssueController@getOptions');
    Route::get('issue/search', 'IssueController@search');

    Route::post('issue/filter', 'IssueController@saveFilter');
    Route::get('issue/filters', 'IssueController@getFilters');
    Route::get('issue/filters/reset', 'IssueController@resetFilters');
    Route::post('issue/filters', 'IssueController@editFilters');

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
    Route::post('issue/{id}/labels', [ 'uses' => 'IssueController@setLabels' ]); // this middleware is put into the action
    Route::post('issue/{id}/move', [ 'middleware' => 'privilege:move_issue', 'uses' => 'IssueController@move' ]);
    Route::post('issue/copy', [ 'middleware' => 'privilege:create_issue', 'uses' => 'IssueController@copy' ]);
    Route::post('issue/{id}/convert', [ 'middleware' => 'privilege:edit_issue', 'uses' => 'IssueController@convert' ]);
    Route::get('issue/{id}/reset', [ 'middleware' => 'privilege:reset_issue', 'uses' => 'IssueController@resetState' ]);

    Route::get('issue/{id}/wfactions', 'IssueController@wfactions');

    Route::resource('issue/{id}/comments', 'CommentsController');
    Route::resource('issue/{id}/worklog', 'WorklogController');

    Route::get('issue/{id}/gitcommits', 'WebhookController@index');

    Route::post('issue/release', 'IssueController@release');

    Route::post('link', 'LinkController@store');
    Route::delete('link/{id}', 'LinkController@destroy');

    Route::post('file', [ 'middleware' => 'privilege:upload_file', 'uses' => 'FileController@upload' ]);
    Route::get('file/{id}/thumbnail', 'FileController@downloadThumbnail');
    Route::get('file/{id}', [ 'middleware' => 'privilege:download_file', 'uses' => 'FileController@download' ]);
    Route::delete('file/{id}', [ 'middleware' => 'privilege:remove_file', 'uses' => 'FileController@delete' ]);

    Route::post('document/{id}/upload',  [ 'middleware' => 'privilege:upload_file', 'uses' => 'DocumentController@upload' ]);
    Route::get('document/{id}/download', [ 'middleware' => 'privilege:download_file', 'uses' => 'DocumentController@download' ]);
    Route::get('document/options', 'DocumentController@getOptions');
    Route::get('document/directory/{id}', 'DocumentController@index');
    Route::get('document/search/path', 'DocumentController@searchPath');
    Route::post('document/move', 'DocumentController@move');
    Route::post('document/{id}', [ 'middleware' => 'privilege:manage_project', 'uses' => 'DocumentController@createFolder' ]);
    Route::put('document/{id}', 'DocumentController@update');
    Route::delete('document/{id}', 'DocumentController@destroy');

    Route::post('wiki/{id}/upload',  [ 'middleware' => 'privilege:upload_file', 'uses' => 'WikiController@upload' ]);
    Route::get('wiki/{id}/download', [ 'middleware' => 'privilege:download_file', 'uses' => 'WikiController@download2' ]);
    Route::get('wiki/{id}/file/{fid}/download', [ 'middleware' => 'privilege:download_file', 'uses' => 'WikiController@download' ]);
    Route::delete('wiki/{id}/file/{fid}', [ 'middleware' => 'privilege:remove_file', 'uses' => 'WikiController@remove' ]);
    Route::get('wiki/directory/{id}', 'WikiController@index');
    Route::get('wiki/search/path', 'WikiController@searchPath');
    Route::get('wiki/{id}', 'WikiController@show');
    Route::post('wiki/move', 'WikiController@move');
    Route::post('wiki/copy', 'WikiController@copy');
    Route::post('wiki', 'WikiController@create');
    Route::put('wiki/{id}', 'WikiController@update');
    Route::get('wiki/{id}/checkin', 'WikiController@checkin');
    Route::get('wiki/{id}/checkout', 'WikiController@checkout');
    Route::delete('wiki/{id}', 'WikiController@destroy');

    Route::resource('kanban', 'BoardController');
    Route::get('kanban/{id}/access', 'BoardController@recordAccess');
    Route::post('kanban/{id}/rank', 'BoardController@setRank');

    Route::post('sprint', 'SprintController@store');
    Route::post('sprint/moveissue', 'SprintController@moveIssue');
    Route::post('sprint/{no}/publish', 'SprintController@publish');
    Route::post('sprint/{no}/complete', 'SprintController@complete');
    Route::get('sprint/{no}/log', 'SprintController@getLog');
    Route::get('sprint/{no}', 'SprintController@show');
    Route::delete('sprint/{no}', 'SprintController@destroy');

    // project module config
    Route::post('epic/{id}/delete', 'EpicController@delete');
    Route::resource('epic', 'EpicController');
    Route::post('epic/batch', 'EpicController@handle');
});
