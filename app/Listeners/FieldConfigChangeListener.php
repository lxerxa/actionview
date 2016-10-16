<?php

namespace App\Listeners;

use App\Events\Event;
use App\Events\FieldChangeEvent;
use App\Events\FieldDeleteEvent;

use App\Customization\Eloquent\Field;
use App\Customization\Eloquent\Screen;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
                    $new_field = Field::Find($field_id, ['name', 'key', 'type', 'defaultValue', 'optionValues'])->toArray();
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
}
