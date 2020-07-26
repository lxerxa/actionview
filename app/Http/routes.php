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

$api_prefix = 'actionview/api';

Route::get('/', function () {
    return view('welcome');
});

// session router
Route::post($api_prefix . '/session', 'SessionController@create');
Route::get($api_prefix . '/session', 'SessionController@getSess');
Route::delete($api_prefix . '/session', 'SessionController@destroy');

Route::post($api_prefix . '/user/register', 'UserController@register');

Route::post($api_prefix .'/user/resetpwdsendmail', 'UserController@sendMailForResetpwd');
Route::get($api_prefix . '/user/resetpwd', 'UserController@showResetpwd');
Route::post($api_prefix . '/user/resetpwd', 'UserController@doResetpwd');
// holida api
Route::get($api_prefix . '/holiday/{year}', 'HolidayController@index');
// webhook api
Route::post($api_prefix . '/webhook/{type}/project/{key}', 'WebhookController@exec');

Route::group([ 'prefix' => $api_prefix, 'middleware' => 'can' ], function () {
    // project route
    Route::get('myproject', 'ProjectController@myproject');
    Route::get('project/recent', 'ProjectController@recent');
    Route::get('project', 'ProjectController@index');
    Route::get('project/checkkey/{key}', 'ProjectController@checkKey');
    Route::get('project/options', 'ProjectController@getOptions');
    Route::get('project/search', 'ProjectController@search');
    Route::get('project/{key}', 'ProjectController@show');
    Route::post('project', 'ProjectController@store');
    Route::put('project/{id}', 'ProjectController@update');
    Route::get('project/{id}/createindex', 'ProjectController@createIndex');
    Route::post('project/batch/status', 'ProjectController@updMultiStatus');
    Route::post('project/batch/createindex', 'ProjectController@createMultiIndex');
    Route::delete('project/{id}', 'ProjectController@destroy');

    Route::get('user/search', 'UserController@search');
    Route::get('user/{id}/renewpwd', 'UserController@renewPwd');
    Route::post('user/batch/delete', 'UserController@delMultiUsers');
    Route::post('user/batch/invalidate', 'UserController@invalidateMultiUsers');
    Route::post('user/fileupload', 'UserController@upload');
    Route::post('user/imports', 'UserController@imports');
    Route::resource('user', 'UserController');

    Route::get('logs', 'AccessLogsController@index');

    Route::get('calendar/{year}', 'CalendarController@index');
    Route::post('calendar', 'CalendarController@update');
    Route::post('calendar/sync', 'CalendarController@sync');

    Route::get('group/search', 'GroupController@search');
    Route::post('group/batch/delete', 'GroupController@delMultiGroups');
    Route::resource('group', 'GroupController');

    Route::get('directory/{id}/test', 'DirectoryController@test');
    Route::get('directory/{id}/sync', 'DirectoryController@sync');
    Route::resource('directory', 'DirectoryController');

    Route::get('mysetting', 'MysettingController@show');
    Route::post('mysetting/account', 'MysettingController@updAccounts');
    Route::post('mysetting/resetpwd', 'MysettingController@resetPwd');
    Route::post('mysetting/notify', 'MysettingController@setNotifications');
    Route::post('mysetting/favorite', 'MysettingController@setFavorites');
    Route::post('mysetting/avatar', 'MysettingController@setAvatar');

    // middleware is put into controller
    Route::get('syssetting', 'SyssettingController@show');
    Route::post('syssetting', 'SyssettingController@update');
    Route::post('syssetting/restpwd', 'SyssettingController@resetPwd');
    Route::post('syssetting/sendtestmail', 'SyssettingController@sendTestMail');

    Route::get('getavatar', 'FileController@getAvatar');
    Route::post('tmpfile', 'FileController@uploadTmpFile');
});

// project config
Route::group([ 'prefix' => $api_prefix . '/project/{project_key}', 'middleware' => [ 'can', 'privilege:manage_project' ] ], function () {
    // project type config
    Route::resource('type', 'TypeController');
    Route::post('type/batch', 'TypeController@handle');
    // project field config
    Route::resource('field', 'FieldController');
    Route::get('field/{id}/used', 'FieldController@viewUsedInProject');
    // project screen config
    Route::resource('screen', 'ScreenController');
    Route::get('screen/{id}/used', 'ScreenController@viewUsedInProject');
    // project workflow config
    Route::resource('workflow', 'WorkflowController');
    Route::get('workflow/{id}/used', 'WorkflowController@viewUsedInProject');
    // project role config
    Route::get('role/{id}/reset', 'RoleController@reset');
    Route::post('role/{id}/permissions', 'RoleController@setPermissions');
    Route::post('role/{id}/actor', 'RoleController@setActor');
    Route::post('role/{id}/groupactor', 'RoleController@setGroupActor');
    Route::resource('role', 'RoleController');
    Route::get('role/{id}/used', 'RoleController@viewUsedInProject');
    // project event config
    Route::get('events/{event_id}/reset', 'EventsController@reset');
    Route::post('events/{event_id}/notify', 'EventsController@setNotify');
    Route::resource('events', 'EventsController');
    // project priority config
    Route::resource('priority', 'PriorityController');
    Route::get('priority/{id}/used', 'PriorityController@viewUsedInProject');
    Route::post('priority/batch', 'PriorityController@handle');
    // project state config
    Route::resource('state', 'StateController');
    Route::post('state/batch', 'StateController@handle');
    Route::get('state/{id}/used', 'StateController@viewUsedInProject');
    // project resolution config
    Route::resource('resolution', 'ResolutionController');
    Route::get('resolution/{id}/used', 'ResolutionController@viewUsedInProject');
    Route::post('resolution/batch', 'ResolutionController@handle');

    Route::get('integrations', 'ExternalUsersController@index');
    Route::post('integrations', 'ExternalUsersController@handle');

    Route::resource('webhooks', 'WebhooksController');

    Route::post('labels/{id}/delete', 'LabelsController@delete');
    Route::resource('labels', 'LabelsController');
});

Route::group([ 'prefix' => $api_prefix . '/project/{project_key}', 'middleware' => [ 'can', 'privilege:view_project' ] ], function () {
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
    Route::get('report/timetracks', 'ReportController@getTimetracks');
    Route::get('report/timetracks/issue/{issue_id}', 'ReportController@getTimetracksDetail');
    Route::get('report/regressions', 'ReportController@getRegressions');
    Route::get('report/issues', 'ReportController@getIssues');
    Route::get('report/{mode}/filters/reset', 'ReportController@resetSomeFilters');
    Route::post('report/{mode}/filters', 'ReportController@editSomeFilters');
    Route::post('report/{mode}/filter', 'ReportController@saveFilter');
    // project team
    Route::get('team', 'RoleController@index');
    // preview the workflow chart
    Route::get('workflow/{id}/preview', 'WorkflowController@preview');

    Route::get('issue/options', 'IssueController@getOptions');
    Route::get('issue/search', 'IssueController@search');

    Route::post('issue/filter', 'IssueController@saveIssueFilter');
    Route::get('issue/filters', 'IssueController@getIssueFilters');
    Route::get('issue/filters/reset', 'IssueController@resetIssueFilters');
    Route::post('issue/filters', 'IssueController@editFilters');

    Route::post('issue/columns', 'IssueController@setDisplayColumns');
    Route::post('issue/columns/reset', 'IssueController@resetDisplayColumns');

    Route::post('issue/imports', 'IssueController@imports');

    Route::post('issue/batch', 'IssueController@batchHandle');

    Route::get('issue/{id}', 'IssueController@show');
    Route::get('issue', 'IssueController@index');
    Route::post('issue', [ 'middleware' => 'privilege:create_issue', 'uses' => 'IssueController@store' ]);
    // this middleware is put into action
    Route::put('issue/{id}', 'IssueController@update');
    Route::delete('issue/{id}', [ 'middleware' => 'privilege:delete_issue', 'uses' => 'IssueController@destroy' ]);

    Route::get('issue/{id}/history', 'IssueController@getHistory');
    Route::post('issue/{id}/watching', 'IssueController@watch');

    Route::post('issue/{id}/workflow/{workflow_id}', [ 'middleware' => 'privilege:exec_workflow', 'uses' => 'IssueController@doAction' ]);
    Route::post('issue/{id}/assign', [ 'uses' => 'IssueController@setAssignee' ]); // this middleware is put into the action
    Route::post('issue/{id}/labels', [ 'uses' => 'IssueController@setLabels' ]); // this middleware is put into the action
    Route::post('issue/{id}/move', [ 'middleware' => 'privilege:move_issue', 'uses' => 'IssueController@move' ]);
    Route::post('issue/copy', [ 'middleware' => 'privilege:create_issue', 'uses' => 'IssueController@copy' ]);
    Route::post('issue/{id}/convert', [ 'middleware' => 'privilege:edit_issue', 'uses' => 'IssueController@convert' ]);
    Route::post('issue/{id}/reset', [ 'middleware' => 'privilege:reset_issue', 'uses' => 'IssueController@resetState' ]);

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
    Route::delete('file/{id}', 'FileController@delete');

    Route::post('document/{id}/upload', 'DocumentController@upload');
    Route::get('document/{id}/download', 'DocumentController@download');
    Route::get('document/{id}/downloadthumbnails', 'DocumentController@downloadThumbnails');
    Route::get('document/options', 'DocumentController@getOptions');
    Route::get('document/directory/{id}', 'DocumentController@index');
    Route::get('document/search/path', 'DocumentController@searchPath');
    Route::post('document/move', 'DocumentController@move');
    Route::post('document/{id}', [ 'middleware' => 'privilege:manage_project', 'uses' => 'DocumentController@createFolder' ]);
    Route::put('document/{id}', 'DocumentController@update');
    Route::delete('document/{id}', 'DocumentController@destroy');
    Route::get('document/dirtree', 'DocumentController@getDirTree');
    Route::get('document/{id}/dirs', 'DocumentController@getDirChildren');

    Route::get('wiki/dirtree', 'WikiController@getDirTree');
    Route::get('wiki/{id}/dirs', 'WikiController@getDirChildren');
    Route::post('wiki/{id}/upload', 'WikiController@upload');
    Route::get('wiki/{id}/download', 'WikiController@download2');
    Route::get('wiki/{id}/file/{fid}/download', 'WikiController@download');
    Route::delete('wiki/{id}/file/{fid}', 'WikiController@remove');
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
    Route::put('sprint/{no}', 'SprintController@update');
    Route::post('sprint/{no}/complete', 'SprintController@complete');
    Route::get('sprint/{no}/log', 'SprintController@getLog');
    Route::get('sprint/{no}', 'SprintController@show');
    Route::delete('sprint/{no}', 'SprintController@destroy');

    // project module config
    Route::post('epic/{id}/delete', 'EpicController@delete');
    Route::resource('epic', 'EpicController');
    Route::post('epic/batch', 'EpicController@handle');
});
