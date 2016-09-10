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

Route::resource('project', [ 'middleware' => [ 'can', 'privilege:global:manange_project' ], 'uses' => 'ProjectController' ]);
Route::resource('user', [ 'middleware' => [ 'can', 'privilege:global:manange_user' ] , 'uses' => 'UserController' ]);
// get project users
Route::get('project/{project_key}/users', [ 'middleware' => [ 'can', 'privilege:project:any' ], 'uses' => 'ProjectController@users' ]);

// session router
Route::post('session', 'SessionController@create');
Route::delete('session', [ 'middleware' => 'can', 'uses' => 'SessionController@destroy' ]);

// project config
Route::group([ 'prefix' => 'project/{project_key}', 'middleware' => [ 'can', 'privilege:admin_project' ] ], function () {
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
});

Route::get('project/{project_key}/issue', [ 'middleware' => [ 'can', 'privilege:project:any' ], 'uses' => 'IssueController@index' ]);
Route::post('project/{project_key}/issue', [ 'middleware' => [ 'can', 'privilege:project:create_issue' ], 'uses' => 'IssueController@store' ]);
Route::put('project/{project_key}/issue/{id}', [ 'middleware' => [ 'can', 'privilege:project:edit_issue' ], 'uses' => 'IssueController@update' ]);
Route::delete('project/{project_key}/issue/{id}', [ 'middleware' => [ 'can', 'privilege:project:delete_issue' ], 'uses' => 'IssueController@destroy' ]);

Route::resource('project/{project_key}/issue',  [ 'middleware' => [ 'can' ], 'uses' => 'UserController' ]);
