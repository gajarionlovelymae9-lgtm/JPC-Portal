<?php

namespace App\Services\AiReports\Ai\Exceptions;

use RuntimeException;

/**
 * The chosen AI provider is misconfigured (missing endpoint or credentials).
 * Signals an environment problem, not a document problem.
 */
class AiProviderConfigurationException extends RuntimeException {}
