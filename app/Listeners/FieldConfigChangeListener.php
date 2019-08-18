<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\FieldChangeEvent;
use App\Events\FieldDeleteEvent;

use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use App\Project\Eloquent\UserIssueListColumns;
use App\Project\Eloquent\ProjectIssueListColumns;
use App\Project\Eloquent\Board;
use App\Project\Eloquent\Project;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use DB;

class FieldConfigChangeListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  FieldChangeEvent  $event
     * @return void
     */
    public function handle(Event $event)
    {
        if ($event instanceof FieldChangeEvent)
        {
            $this->updateSchema($event->field_id, 1);
        }
        else if ($event instanceof FieldDeleteEvent)
        {
            $this->updateSchema($event->field_id, 2);
            $this->updateDisplayColumns($event->project_key, $event->field_key);
            $this->updateKanbanDisplayFields($event->project_key, $event->field_key);
            $this->unsetIssueVal($event->project_key, $event->field_key, $event->field_type);
        }
    }

    /**
     * update the schema.
     *
     * @param  string  $field_id
     * @param  int flag
     * @return void
     */
    public function updateSchema($field_id, $flag)
    {
        $screens = Screen::whereRaw([ 'field_ids' => $field_id ])->get([ 'schema' ]);
        foreach ($screens as $screen)
        {
            $new_schema = [];
            foreach ($screen->schema as $field)
            {
                if ($field['_id'] != $field_id)
                {
                    $new_schema[] = $field;
                    continue;
                }

                if ($flag == 1)
                {
                    $new_field = Field::Find($field_id, ['name', 'key', 'type', 'applyToTypes', 'defaultValue', 'optionValues'])->toArray();
                    if (isset($field['required']) && $field['required'])
                    {
                        $new_field['required'] = true;
                    }
                    $new_schema[] = $new_field;;
                }
            }
            $screen->schema = $new_schema;
            $screen->field_ids = array_column($new_schema, '_id');
            $screen->save();
        }
    }

    /**
     * unset the issue value of this field.
     *
     * @param  string  $project_key
     * @param  string  $field_id
     * @return void
     */
    public function unsetIssueVal($project_key, $field_key, $field_type)
    {
        $res = [];
        if ($project_key === '$_sys_$')
        {
            $projects = Project::all();
            foreach($projects as $project)
            {
                DB::collection('issue_' . $project->key)->whereRaw([ $field_key => [ '$exists' => 1 ] ])->unset($field_key);

                if ($field_type === 'MultiUser')
                {
                    DB::collection('issue_' . $project->key)->whereRaw([ $field_key . '_ids' => [ '$exists' => 1 ] ])->unset($field_key . '_ids');
                }
                else if ($field_type === 'TimeTracking')
                {
                    DB::collection('issue_' . $project->key)->whereRaw([ $field_key . '_m' => [ '$exists' => 1 ] ])->unset($field_key . '_m');
                }
            }
        }
        else
        {
            DB::collection('issue_' . $project_key)->whereRaw([ $field_key => [ '$exists' => 1 ] ])->unset($field_key);

            if ($field_type === 'MultiUser')
            {
                DB::collection('issue_' . $project_key)->whereRaw([ $field_key . '_ids' => [ '$exists' => 1 ] ])->unset($field_key . '_ids');
            }
            else if ($field_type === 'TimeTracking')
            {
                DB::collection('issue_' . $project_key)->whereRaw([ $field_key . '_m' => [ '$exists' => 1 ] ])->unset($field_key . '_m');
            }
        }
    }

    /**
     * update the kanban card display fields.
     *
     * @param  string  $project_key
     * @param  string  $field_id
     * @return void
     */
    public function updateKanbanDisplayFields($project_key, $field_key)
    {
        $res = [];
        if ($project_key === '$_sys_$')
        {
            $res = Board::whereRaw([ 'display_fields' => $field_key ])->get();
        }
        else
        {
            $res = Board::whereRaw([ 'display_fields' => $field_key, 'project_key' => $project_key ])->get();
        }
        foreach ($res as $value)
        {
            $new_fields = [];
            $display_fields = isset($value->display_fields) ? $value->display_fields : [];
            foreach ($display_fields as $val)
            {
                if ($val === $field_key)
                {
                    continue;
                }

                $new_fields[] = $val;
            }

            $value->display_fields = $new_fields;
            $value->save();
        }
    }

    /**
     * update the issue list display columns.
     *
     * @param  string  $project_key
     * @param  string  $field_id
     * @return void
     */
    public function updateDisplayColumns($project_key, $field_key)
    {
        $res = [];
        if ($project_key === '$_sys_$')
        {
            $res = UserIssueListColumns::whereRaw([ 'column_keys' => $field_key ])->get();
        }
        else
        {
            $res = UserIssueListColumns::whereRaw([ 'column_keys' => $field_key, 'project_key' => $project_key ])->get();
        }
        foreach ($res as $value)
        {
            $new_columns = [];
            $column_keys = [];
            $columns = isset($value->columns) ? $value->columns : [];
            foreach ($columns as $column)
            {
                if ($column['key'] === $field_key)
                {
                    continue;
                }

                $new_columns[] = $column;
                $column_keys[] = $column['key'];
            }

            $value->columns = $new_columns;
            $value->column_keys = $column_keys;
            $value->save();
        }

        if ($project_key === '$_sys_$')
        {
            $res = ProjectIssueListColumns::whereRaw([ 'column_keys' => $field_key ])->get();
        }
        else
        {
            $res = ProjectIssueListColumns::whereRaw([ 'column_keys' => $field_key, 'project_key' => $project_key ])->get();
        }
        foreach ($res as $value)
        {
            $new_columns = [];
            $column_keys = [];
            $columns = isset($value->columns) ? $value->columns : [];
            foreach ($columns as $column)
            {
                if ($column['key'] === $field_key)
                {
                    continue;
                }

                $new_columns[] = $column;
                $column_keys[] = $column['key'];
            }

            $value->columns = $new_columns;
            $value->column_keys = $column_keys;
            $value->save();
        }
    }
}
