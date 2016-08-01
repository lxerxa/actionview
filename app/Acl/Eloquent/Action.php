<?php

namespace App\Acl\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class Action {

    const CREATE_ISSUE = 'create_issue';
    const ASSIGN_ISSUE = 'assign_issue';
    const ASSIGNED_ISSUE = 'assigned_issue';
    const CLOSE_ISSUE = 'colse_issue';
    const DELETE_ISSUE = 'delete_issue';
    const EDIT_ISSUE = 'edit_issue';

    /**
     * Return an object representing all actions.
     *
     * @return Actions
     */
    public static function all()
    {
        return new static([
            static::CREATE_ISSUE,
            static::ASSIGN_ISSUE,
            static::ASSIGNED_ISSUE,
            static::CLOSE_ISSUE,
            static::DELETE_ISSUE,
            static::EDIT_ISSUE,
        ]);
    }

}
