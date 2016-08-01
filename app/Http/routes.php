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

//Route::resource('user', 'UserController');

Route::resource('project', 'ProjectController');

Route::resource('user', 'UserController');

Route::group(['prefix' => 'project/{project_key}'], function () {
    Route::resource('type', 'TypeController');
    Route::post('type/batch', 'TypeController@handle');

    Route::resource('field', 'FieldController');
    Route::resource('screen', 'ScreenController');
    Route::resource('workflow', 'WorkflowController');
    Route::resource('role', 'RoleController');

    Route::resource('priority', 'PriorityController');
    Route::post('priority/batch', 'PriorityController@handle');

    Route::resource('state', 'StateController');

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
