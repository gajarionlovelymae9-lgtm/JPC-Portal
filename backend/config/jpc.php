<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development Seeder Data
    |--------------------------------------------------------------------------
    |
    | The Development* seeders populate large fake datasets (users, employees,
    | tasks, reports, achievements, ...). They MUST never run against a
    | production database.
    |
    | Enabled when APP_ENV is not "production". In production the seeder only
    | runs when SEED_DEVELOPMENT_DATA=true is set explicitly.
    |
    */

    'seed_development' => [
        'enabled' => (bool) env('SEED_DEVELOPMENT_DATA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Passwords
    |--------------------------------------------------------------------------
    |
    | Passwords for the four development accounts. These come from environment
    | variables and fall back to a single development-only password so the
    | Phase 2/3 test suites and local testers can authenticate.
    |
    | DEVELOPMENT ONLY. Never commit production credentials here.
    |
    */

    'dev_passwords' => [
        'admin' => env('SEED_ADMIN_PASSWORD', 'P@ssw0rd2026'),
        'hr' => env('SEED_HR_PASSWORD', 'P@ssw0rd2026'),
        'department_head' => env('SEED_DEPARTMENT_HEAD_PASSWORD', 'P@ssw0rd2026'),
        'employee' => env('SEED_EMPLOYEE_PASSWORD', 'P@ssw0rd2026'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Academic Year
    |--------------------------------------------------------------------------
    |
    | The academic year used by the development sample dataset. Values are
    | configurable so the school's official calendar does not have to be
    | hardcoded into application logic.
    |
    */

    'academic_year' => [
        'code' => env('SEED_ACADEMIC_YEAR_CODE', '2026-2027'),
        'start_date' => env('SEED_ACADEMIC_YEAR_START', '2026-06-01'),
        'end_date' => env('SEED_ACADEMIC_YEAR_END', '2027-05-31'),
    ],
];
