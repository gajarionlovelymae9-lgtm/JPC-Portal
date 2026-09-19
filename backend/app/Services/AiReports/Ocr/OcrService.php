<?php

namespace App\Services\AiReports\Ocr;

use App\Models\OcrProcessing;
use App\Models\ReportFile;
use App\Services\AiReports\Ocr\Contracts\OcrProvider;
use Throwable;

/**
 * Orchestrates OCR execution for a report document.
 *
 * Each attempt writes an immutable ocr_processings row; retries after a
 * failure create a fresh attempt (history preserved). Successful runs are
 * reused so re-dispatching never duplicates work.
 */
class OcrService
{
    public function __construct(
        protected OcrProvider $provider,
    ) {}

    /**
     * Run OCR for the file, returning the completed processing record.
     *
     * @throws Throwable
     */
    public function process(ReportFile $file, string $language = 'en'): OcrProcessing
    {
        $latest = $file->ocrProcessings()->latest('id')->first();

        if ($latest && in_array($latest->status, ['completed', 'processing'], true)) {
            return $latest;
        }

        $processing = new OcrProcessing;
        $processing->forceFill([
            'report_file_id' => $file->id,
            'provider' => $this->provider->name(),
            'status' => 'processing',
            'language' => $language,
            'started_at' => now(),
        ])->save();

        try {
            $result = $this->provider->extract($file);

            $processing->forceFill([
                'status' => 'completed',
                'raw_text' => $result->rawText,
                'confidence_score' => $result->confidence,
                'language' => $result->language ?? $language,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $processing->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $processing;
    }
}