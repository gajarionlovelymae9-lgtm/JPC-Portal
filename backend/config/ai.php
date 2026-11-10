<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Configures the AI provider used for summarization, requirement
    | matching, reference analysis and consistency checks. Credentials
    | are sourced from the environment; nothing is hard-coded here.
    |
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'fake'),
        'model' => env('AI_MODEL', 'gpt-4o'),
        'api_key' => env('AI_API_KEY', ''),
        'base_url' => env('AI_BASE_URL', ''),

        /* Default summary length: short|medium|detailed */
        'default_summary_level' => env('AI_DEFAULT_SUMMARY_LEVEL', 'short'),

        'timeout' => (int) env('AI_TIMEOUT', 420),
        'tries' => (int) env('AI_TRIES', 3),
        'backoff' => (int) env('AI_BACKOFF', 5),

        /* Guardrails */
        'prompt_injection_guard' => env('AI_PROMPT_INJECTION_GUARD', true),
        'output_schema_validation' => env('AI_OUTPUT_SCHEMA_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | JPC AIDE Assistant Limits & Access
    |--------------------------------------------------------------------------
    |
    | Role-based AI access and daily usage limits for JPC Portal AI assistant
    | features. Limits are enforced server-side and cannot be bypassed by
    | frontend requests. Blocked/invalid requests must not consume usage.
    |
    */
    'assistant' => [
        'allowed_roles' => ['admin', 'employee'],

        'daily_limits' => [
            'employee' => (int) env('AI_DAILY_LIMIT_EMPLOYEE', 20),
            'admin' => (int) env('AI_DAILY_LIMIT_ADMIN', 30),
        ],

        // Timezone used to calculate the daily usage window/reset.
        'reset_timezone' => env('AI_USAGE_RESET_TIMEZONE', config('app.timezone', 'UTC')),
    ],

];
