<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deadline Reminders
    |--------------------------------------------------------------------------
    |
    | Days before the due date at which the scheduler sends a "deadline
    | reminder". Every value maps to a distinct reminder_days bucket in the
    | notifications table, so each threshold is sent at most once per task.
    |
    */

    'reminders' => [7, 3, 1, 0],

    /*
    |--------------------------------------------------------------------------
    | Notification Recipients
    |--------------------------------------------------------------------------
    |
    | Management roles that receive the weekly-monitoring overdue alert when an
    | employee's assignment becomes overdue. Overdue alerts are deduplicated
    | per (user, task) via the sentinel reminder_days = -1 record.
    |
    */

    'notifications' => [
        'overdue_management_roles' => ['department_head'],
    ],
];