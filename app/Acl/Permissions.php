<?php

namespace App\Acl;

class Permissions {

    const ADMIN_PROJECT = 'admin_project';

    const ASSIGNED_ISSUE = 'assigned_issue';
    const ASSIGN_ISSUE = 'assign_issue';

    const CREATE_ISSUE = 'create_issue';
    const EDIT_ISSUE = 'edit_issue';
    const DELETE_ISSUE = 'delete_issue';

    const ADD_COMMNETS = 'add_comments';
    const EDIT_COMMNETS = 'edit_comments';
    const DELETE_COMMNETS = 'delete_comments';

    const ADD_ATTACHMENT = 'add_attachment';
    const DELETE_ATTACHMENT = 'delete_attachment';

    /**
     * Return an object representing all actions.
     *
     * @return Permissions
     */
    public static function all()
    {
        return [
            static::ADMIN_PROJECT,
            static::ASSIGNED_ISSUE,
            static::ASSIGN_ISSUE,
            static::CREATE_ISSUE,
            static::EDIT_ISSUE,
            static::DELETE_ISSUE,
            static::ADD_COMMNETS,
            static::EDIT_COMMNETS,
            static::DELETE_COMMNETS,
            static::ADD_ATTACHMENT,
            static::DELETE_ATTACHMENT,
        ];
    }

}
