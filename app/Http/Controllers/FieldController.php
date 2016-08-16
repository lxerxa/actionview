<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

use App\Events\FieldChangeEvent;
use App\Events\FieldDeleteEvent;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;

class FieldController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($project_key)
    {
        $fields = Field::where([ 'project_key' => $project_key ])->orderBy('created_at', 'asc')->get(['key', 'name', 'type', 'description']);
        foreach ($fields as $key => $field)
        {
            $fields[$key]->screens = Screen::whereRaw([ 'field_ids' => $field->id ])->get(['name']);
        }
        return Response()->json(['ecode' => 0, 'data' => $fields]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $project_key)
    {
        $name = $request->input('name');
        if (!$name || trim($name) == '')
        {
            throw new \UnexpectedValueException('the name cannot be empty.', -10002);
        }

        $key = $request->input('key');
        if (!$key || trim($key) == '')
        {
            throw new \InvalidArgumentException('field key cannot be empty.', -10002);
        }
        if (Field::where('key', $key)->where('project_key', $project_key)->exists())
        {
            throw new \InvalidArgumentException('field key cannot be repeated.', -10002);
        }

        $field = Field::create($request->all() + [ 'project_key' => $project_key ]);
        return Response()->json(['ecode' => 0, 'data' => $field]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($project_key, $id)
    {
        $field = Field::find($id);
        if (!$field || $project_key != $field->project_key)
        {
            throw new \UnexpectedValueException('the field does not exist or is not in the project.', -10002);
        }
        // get related screen
        $field->screens = Screen::whereRaw([ 'field_ids' => $id ])->get(['name']);

        return Response()->json(['ecode' => 0, 'data' => $field]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $project_key, $id)
    {
        $name = $request->input('name');
        if (isset($name))
        {
            if (!$name || trim($name) == '')
            {
                throw new \UnexpectedValueException('the name can not be empty.', -10002);
            }
        }
        $field = Field::find($id);
        if (!$field || $project_key != $field->project_key)
        {
            throw new \UnexpectedValueException('the field does not exist or is not in the project.', -10002);
        }
        $field->fill($request->except(['project_key', 'key', 'type']))->save();

        Event::fire(new FieldChangeEvent($id));

        return Response()->json(['ecode' => 0, 'data' => Field::find($id)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_key, $id)
    {
        $field = Field::find($id);
        if (!$field || $project_key != $field->project_key)
        {
            throw new \UnexpectedValueException('the field does not exist or is not in the project.', -10002);
        }
        Field::destroy($id);
        Event::fire(new FieldDeleteEvent($id));
        return Response()->json(['ecode' => 0, 'data' => ['id' => $id]]);
    }
}
