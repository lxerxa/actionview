<?php

namespace App\Acl;

class Permissions {

    const VIEW_PROJECT       = 'view_project';
    const MANAGE_PROJECT      = 'manage_project';

    const ASSIGNED_ISSUE      = 'assigned_issue';
    const ASSIGN_ISSUE        = 'assign_issue';

    const CREATE_ISSUE        = 'create_issue';
    const EDIT_ISSUE          = 'edit_issue';
    const EDIT_SELF_ISSUE     = 'edit_self_issue';
    const DELETE_ISSUE        = 'delete_issue';
    const LINK_ISSUE          = 'link_issue';
    const MOVE_ISSUE          = 'move_issue';
    const RESOLVE_ISSUE       = 'resolve_issue';
    const RESET_ISSUE         = 'reset_issue';
    const CLOSE_ISSUE         = 'close_issue';

    //const VIEW_WORKFLOW       = 'view_workflow';
    const EXEC_WORKFLOW       = 'exec_workflow';

    const UPLOAD_FILE         = 'upload_file';
    const DOWNLOAD_FILE       = 'download_file';
    const REMOVE_FILE         = 'remove_file';
    const REMOVE_SELF_FILE    = 'remove_self_file';

    const ADD_COMMNETS        = 'add_comments';
    const EDIT_COMMNETS       = 'edit_comments';
    const EDIT_SELF_COMMNETS  = 'edit_self_comments';
    const DELETE_COMMNETS     = 'delete_comments';
    const DELETE_SELF_COMMNETS= 'delete_self_comments';

    const ADD_WORKLOG         = 'add_worklog';
    const EDIT_WORKLOG        = 'edit_worklog';
    const EDIT_SELF_WORKLOG   = 'edit_self_worklog';
    const DELETE_WORKLOG      = 'delete_worklog';
    const DELETE_SELF_WORKLOG = 'delete_self_worklog';

    /**
     * Return an object representing all actions.
     *
     * @return Permissions
     */
    public static function all()
    {
        return [
            static::VIEW_PROJECT,
            static::MANAGE_PROJECT,

            static::ASSIGNED_ISSUE,
            static::ASSIGN_ISSUE,

            static::CREATE_ISSUE,
            static::EDIT_ISSUE,
            static::EDIT_SELF_ISSUE,
            static::DELETE_ISSUE,
            static::LINK_ISSUE,
            static::MOVE_ISSUE,
            static::RESOLVE_ISSUE,
            static::RESET_ISSUE,
            static::CLOSE_ISSUE,

            //static::VIEW_WORKFLOW,
            static::EXEC_WORKFLOW,

            static::UPLOAD_FILE,
            static::DOWNLOAD_FILE,
            static::REMOVE_FILE,
            static::REMOVE_SELF_FILE,

            static::ADD_COMMNETS,
            static::EDIT_COMMNETS,
            static::EDIT_SELF_COMMNETS,
            static::DELETE_COMMNETS,
            static::DELETE_SELF_COMMNETS,

            static::ADD_WORKLOG,
            static::EDIT_WORKLOG,
            static::EDIT_SELF_WORKLOG,
            static::DELETE_WORKLOG,
            static::DELETE_SELF_WORKLOG,
        ];
    }

}
