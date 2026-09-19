<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Report Processing (Phase 3.3-K)
    |--------------------------------------------------------------------------
    |
    | Configuration for the employee report pipeline: secure file uploads,
    | OCR and AI-assisted validation/summarization. All engine credentials
    | come from the environment — nothing is hard-coded, and provider
    | selections default to deterministic local (fake) implementations so the
    | pipeline is fully testable without external accounts.
    |
    */

    'queue' => env('AI_REPORTS_QUEUE', 'default'),

    'jobs' => [
        'ocr' => [
            'tries' => (int) env('AI_REPORTS_OCR_TRIES', 3),
            'backoff' => [30, 120, 300],
            'timeout' => (int) env('AI_REPORTS_OCR_TIMEOUT', 300),
        ],
        'ai' => [
            'tries' => (int) env('AI_REPORTS_AI_TRIES', 3),
            'backoff' => [60, 240, 600],
            'timeout' => (int) env('AI_REPORTS_AI_TIMEOUT', 420),
        ],
    ],

    'storage' => [
        // Private disk: report documents must never be publicly reachable.
        'disk' => env('REPORT_FILES_DISK', 'local'),
        'directory' => env('REPORT_FILES_DIRECTORY', 'reports'),
    ],

    'upload' => [
        // Maximum accepted report file size in kilobytes.
        'max_file_size_kb' => (int) env('REPORT_MAX_FILE_SIZE_KB', 51200),
        // Server-side MIME map. Served as the source of truth for validation.
        'allowed_mime_types' => [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ],
        'allowed_extensions' => ['pdf', 'docx', 'jpg', 'jpeg', 'png'],
    ],

    'ocr' => [
        // 'fake' (default, deterministic, offline) or 'google_vision'.
        'provider' => env('OCR_PROVIDER', 'fake'),
        'language' => env('OCR_LANGUAGE', 'en'),
        // OCR below this confidence scores a low-evidence flag (0.00-1.00).
        'min_confidence' => (float) env('OCR_MIN_CONFIDENCE', 0.55),
        'google' => [
            'endpoint' => env('GOOGLE_VISION_ENDPOINT', 'https://vision.googleapis.com/v1'),
            'api_key' => env('GOOGLE_VISION_API_KEY'),
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'credentials_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            'features' => ['TEXT_DETECTION'],
        ],
    ],

    'ai' => [
        // 'fake' (default, deterministic, offline), 'openai' or any OpenAI
        // compatible endpoint via AI_ENDPOINT.
        'provider' => env('AI_PROVIDER', 'fake'),
        'model' => env('AI_MODEL', 'jpc-analysis-v1'),
        'http' => [
            'endpoint' => env('AI_ENDPOINT'),
            'api_key' => env('AI_API_KEY'),
            'organization' => env('AI_ORGANIZATION'),
            'timeout' => (int) env('AI_TIMEOUT', 120),
            'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
            'temperature' => (float) env('AI_TEMPERATURE', 0.1),
            'retries' => (int) env('AI_HTTP_RETRIES', 2),
        ],
        // Version marker stamped on every AiAnalysis so reprocessing history
        // stays attributable to a known engine version.
        'analysis_version' => env('AI_ANALYSIS_VERSION', 'v1.0'),
        'summary_levels' => ['short', 'medium', 'detailed'],
        'summary_default_level' => env('AI_SUMMARY_LEVEL', 'medium'),
    ],

    'analysis' => [
        // Guard-rail: the document text handed to the model as DATA is
        // truncated to avoid oversized prompts and keep costs bounded.
        'max_input_chars' => (int) env('AI_REPORTS_MAX_INPUT_CHARS', 60000),
        'completeness_sections' => ['objective', 'methodology', 'results', 'conclusion', 'recommendations'],
        'requirement_matches' => ['matched', 'partially_matched', 'not_matched'],
        'severities' => ['low', 'medium', 'high'],
        'reference' => [
            'anchor_default' => (float) env('REFERENCE_ANCHOR_DEFAULT', 0.35),
            'unverified_label' => 'Reference format detected; source verification unavailable.',
        ],
        // Cost control: when the current version's document hash is unchanged
        // and a completed analysis already exists, re-analysis is a no-op.
        'reuse_completed' => true,
    ],

    'similarity' => [
        // Dice-coefficient thresholds applied to tokenized text.
        'similar_threshold' => (float) env('SIMILARITY_SIMILAR_THRESHOLD', 0.7),
        'highly_similar_threshold' => (float) env('SIMILARITY_HIGH_SIMILAR_THRESHOLD', 0.85),
    ],
];