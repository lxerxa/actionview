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

Route::get('project', [ 'middleware' => 'can', 'uses' => 'ProjectController@index' ]);

Route::resource('user', 'UserController');

// session router
Route::post('session', 'SessionController@create');
Route::delete('session', [ 'middleware' => 'can', 'uses' => 'SessionController@destroy' ]);

// project config
Route::group([ 'prefix' => 'project/{project_key}', 'middleware' => [ 'can', 'permission:admin_project' ] ], function () {
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


/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web']], function () {
    //
});
