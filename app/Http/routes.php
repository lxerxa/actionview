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

Route::post('api/register', 'UserController@register');

Route::get('user/{id}/resetpwd', 'UserController@showResetpwd'); //fix me
Route::post('user/{id}/resetpwd', 'UserController@doResetpwd'); // fix me

Route::group([ 'middleware' => 'can' ], function ()
{
    Route::get('api/project/checkkey/{key}', 'ProjectController@checkKey');
    Route::get('api/myproject', 'ProjectController@myproject');
    Route::get('api/project/options', 'ProjectController@getOptions');
    Route::get('api/project/{id}/createindex', 'ProjectController@createIndex');
    Route::post('api/project/batch/status', 'ProjectController@updMultiStatus');
    Route::post('api/project/batch/createindex', 'ProjectController@createMultiIndex');
    Route::get('api/project/options', 'ProjectController@getOptions');
    Route::resource('api/project', 'ProjectController');

    Route::post('api/user/register', 'UserController@register');
    Route::post('api/user/batch/delete', 'UserController@delMultiUser');
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
    // project module config
    Route::resource('module', 'ModuleController');
    // project version config
    Route::resource('version', 'VersionController');
});

Route::group([ 'prefix' => 'api/project/{project_key}', 'middleware' => [ 'can' ] ], function ()
{
    Route::get('activity', 'ActivityController@index');

    Route::get('issue/searcher', 'IssueController@getSearchers');
    Route::post('issue/searcher', 'IssueController@addSearcher');
    Route::post('issue/searcher/batch', 'IssueController@handleSearcher');
    Route::get('issue/options', 'IssueController@getOptions');
    Route::get('issue/search', 'IssueController@search');
    Route::post('issue/copy', 'IssueController@copy');

    Route::resource('issue', 'IssueController');

    Route::post('issue/{issue_id}/workflow/{workflow_id}/action/{action_id}', 'IssueController@doAction');
    Route::post('issue/{issue_id}/assign', 'IssueController@setAssignee');
    Route::post('issue/{issue_id}/move', 'IssueController@move');
    Route::post('issue/{issue_id}/convert', 'IssueController@convert');
    Route::get('issue/{issue_id}/history', 'IssueController@getHistory');
    Route::post('issue/{issue_id}/watching', 'IssueController@watch');
    Route::get('issue/{issue_id}/reset', 'IssueController@resetState');
    Route::resource('issue/{issue_id}/comments', 'CommentsController');
    Route::resource('issue/{issue_id}/worklog', 'WorklogController');

    Route::post('link', 'LinkController@store');
    Route::delete('link/{id}', 'LinkController@destroy');

    Route::post('file', 'FileController@upload');
    Route::get('file/{id}', 'FileController@download');
    Route::delete('file/{id}', 'FileController@delete');
});
