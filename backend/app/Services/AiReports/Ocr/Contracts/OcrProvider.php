<?php

namespace App\Services\AiReports\Ocr\Contracts;

use App\Models\ReportFile;
use App\Services\AiReports\Ocr\Exceptions\OcrConfigurationException;
use App\Services\AiReports\Ocr\Exceptions\OcrProcessingException;

/**
 * Pluggable OCR engine contract (Phase 3.3-K).
 *
 * Implementations translate a stored report document into machine readable
 * text. Real integrations (e.g. Google Cloud Vision) are environment-gated;
 * the deterministic fake keeps the pipeline offline-friendly and testable.
 */
interface OcrProvider
{
    /**
     * Stable provider identifier persisted to ocr_processings.provider.
     */
    public function name(): string;

    /**
     * Extract text from the uploaded report document.
     *
     * @throws OcrConfigurationException
     * @throws OcrProcessingException
     */
    public function extract(ReportFile $file): OcrResult;
}
