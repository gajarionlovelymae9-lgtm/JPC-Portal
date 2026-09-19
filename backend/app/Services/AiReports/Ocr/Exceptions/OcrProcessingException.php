<?php

namespace App\Services\AiReports\Ocr\Exceptions;

use RuntimeException;

/**
 * OCR could not produce usable text for a specific document.
 */
class OcrProcessingException extends RuntimeException {}