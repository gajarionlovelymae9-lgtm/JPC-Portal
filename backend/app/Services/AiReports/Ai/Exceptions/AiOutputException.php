<?php

namespace App\Services\AiReports\Ai\Exceptions;

use RuntimeException;

/**
 * The AI engine returned content that cannot be validated against the strict
 * output schema. This intentionally forces a failed pipeline state instead of
 * silently trusting model output (hallucination control).
 */
class AiOutputException extends RuntimeException {}
