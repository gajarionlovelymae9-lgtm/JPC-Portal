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

];
