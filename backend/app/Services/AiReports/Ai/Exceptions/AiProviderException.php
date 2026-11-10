<?php

namespace App\Services\AiReports\Ai\Exceptions;

use RuntimeException;

/**
 * The AI provider rejected or failed to complete a request. Treated as a
 * transient failure: the queue job retries with backoff.
 */
class AiProviderException extends RuntimeException {}
