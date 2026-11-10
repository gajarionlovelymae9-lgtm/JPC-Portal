<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Escalation (opt-in)
    |--------------------------------------------------------------------------
    |
    | Escalations are normally raised manually by a Department Head. Optional
    | automatic rules may be turned on and configured by an authorised
    | administrator; they are disabled by default so no school policy threshold
    | is invented by the system.
    |
    */

    'auto_escalation' => [
        'enabled' => (bool) env('JPC_ESCALATION_AUTO_ENABLED', false),
        'thresholds' => [
            'repeated_late_submissions' => env('JPC_ESCALATION_LATE_THRESHOLD'),
            'overdue_days' => env('JPC_ESCALATION_OVERDUE_DAYS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Assignable Roles
    |--------------------------------------------------------------------------
    |
    | Administration roles that may be assigned a responsible person on an
    | escalation and that receive the creation alert.
    |
    */

    'assignable_roles' => ['admin'],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Supporting evidence uploaded with an escalation is stored privately and
    | streamed back only to authorised users.
    |
    */

    'attachment' => [
        'disk' => env('JPC_ESCALATION_DISK', 'local'),
        'max_kb' => 5120,
        'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'],
    ],
];
