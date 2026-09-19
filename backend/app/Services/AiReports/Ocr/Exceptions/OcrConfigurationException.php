<?php

namespace App\Services\AiReports\Ocr\Exceptions;

use RuntimeException;

/**
 * The chosen OCR engine is misconfigured (e.g. Google Vision API key missing).
 * Signals an environment problem, not a document problem.
 */
class OcrConfigurationException extends RuntimeException {}