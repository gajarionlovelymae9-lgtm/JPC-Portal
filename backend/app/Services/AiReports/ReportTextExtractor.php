<?php

namespace App\Services\AiReports;

use App\Models\OcrProcessing;
use App\Models\Report;
use RuntimeException;

/**
 * Assembles the plain text the AI analyzes for a report's latest version.
 *
 * Source of truth is the completed OCR runs attached to the files of the
 * latest report version. Never performs OCR itself — it is only safe to call
 * from the analyze job, which runs after the OCR job in the same chain.
 */
final class ReportTextExtractor
{
    /**
     * @return array{text: string, confidence: float, sources: int}
     */
    public function extract(Report $report): array
    {
        $texts = [];
        $confidences = [];
        $sources = 0;

        foreach ($report->versions()->latest('id')->take(1)->get() as $version) {
            foreach ($version->files as $file) {
                $ocr = $file->ocrProcessings()
                    ->where('status', 'completed')
                    ->latest('id')
                    ->first();

                if (! $ocr instanceof OcrProcessing) {
                    continue;
                }

                $texts[] = (string) $ocr->raw_text;
                $confidences[] = (float) $ocr->confidence_score;
                $sources++;
            }
        }

        $text = trim(implode("\n\n", array_filter($texts)));

        if ($text === '' || $sources === 0) {
            throw new RuntimeException('No completed OCR text is available for the latest report version.');
        }

        $confidence = $confidences === []
            ? 0.0
            : array_sum($confidences) / count($confidences);

        return [
            'text' => $text,
            'confidence' => round(min(1.0, max(0.0, $confidence)), 4),
            'sources' => $sources,
        ];
    }
}
