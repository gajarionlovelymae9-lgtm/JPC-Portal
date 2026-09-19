<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Provider
    |--------------------------------------------------------------------------
    |
    | Configures the OCR engine. Google Cloud Vision is the production
    | provider; a deterministic fake keeps the pipeline offline and
    | fully testable.
    |
    */

    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'fake'),
        'language' => env('OCR_LANGUAGE', 'en'),
        'timeout' => (int) env('OCR_TIMEOUT', 300),
        'tries' => (int) env('OCR_TRIES', 3),
        'backoff' => (int) env('OCR_BACKOFF', 5),

        /* File size cap in kilobytes */
        'max_file_size_kb' => (int) env('OCR_MAX_FILE_SIZE_KB', 10240),

        /* Confidence below this threshold flags OCR_REVIEW_REQUIRED */
        'confidence_threshold' => (float) env('OCR_CONFIDENCE_THRESHOLD', 0.70),
    ],

];
