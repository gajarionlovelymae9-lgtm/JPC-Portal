<?php

namespace App\Services\AiReports\Ocr;

use App\Models\ReportFile;
use App\Services\AiReports\Ocr\Contracts\OcrProvider;
use App\Services\AiReports\Ocr\Contracts\OcrResult;
use Illuminate\Support\Str;

/**
 * Deterministic, offline OCR engine (default engine).
 *
 * Produces stable text that downstream AI analysis and review tests can rely
 * on. DOCX/PDF files use the embedded-text extractor when text is available;
 * otherwise a recognizable synthetic marker string is returned so the full
 * pipeline remains exerciseable without any external service.
 */
class FakeOcrProvider implements OcrProvider
{
    public function __construct(
        protected EmbeddedTextExtractor $extractor,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function extract(ReportFile $file): OcrResult
    {
        $disk = $file->storage_disk ?: config('ai_reports.storage.disk', 'local');
        $path = $file->file_path;
        $fullPath = $this->resolvePath($disk, $path);

        $embedded = $this->embeddedText($file, $fullPath);

        if ($embedded !== null) {
            return new OcrResult(
                rawText: $embedded,
                confidence: 0.97,
                provider: $this->name(),
                source: 'embedded',
                language: config('ai_reports.ocr.language', 'en'),
            );
        }

        $synthetic = $this->syntheticText($file);

        return new OcrResult(
            rawText: $synthetic,
            confidence: 0.85,
            provider: $this->name(),
            source: 'fake',
            language: config('ai_reports.ocr.language', 'en'),
        );
    }

    private function embeddedText(ReportFile $file, ?string $fullPath): ?string
    {
        $lower = strtolower((string) $file->original_filename);

        if (str_ends_with($lower, '.docx')) {
            return $fullPath !== null ? $this->extractor->fromDocx($fullPath) : null;
        }

        if (str_ends_with($lower, '.pdf')) {
            return $fullPath !== null ? $this->extractor->fromPdf($fullPath) : null;
        }

        return null;
    }

    /**
     * Deterministic synthetic document built from stable file attributes.
     */
    private function syntheticText(ReportFile $file): string
    {
        $seed = hash('sha256', $file->id.'|'.$file->original_filename.'|'.($file->file_size ?? 0));
        $variant = ((int) hexdec(substr($seed, 0, 4))) % 3;

        $report = [
            <<<'TEXT'
The report covers the accomplishment of the assigned task for the reporting period. An objective overview is provided together with the methodology used. The results obtained support the main accomplishments and were documented with measurable outcomes. A conclusion summarizes the deliverables and provides recommendations for the next reporting cycle.
TEXT,
            <<<'TEXT'
Overview: this document describes the completed activities and the results achieved during the period. Key accomplishments include delivered outputs, conducted sessions and documented outcomes. The conclusion and recommendations section outlines follow-up actions for the next cycle.
TEXT,
            <<<'TEXT'
Accomplishment report narrative. Objective and methodology are described, followed by the results achieved. The conclusion presents the overall assessment, and a recommendations section sets next steps. Supporting evidence is referenced in the annex.
TEXT,
        ];

        $body = $report[$variant];

        if (str_ends_with(strtolower((string) $file->original_filename), '.png')) {
            $body .= ' [DETECTION_IMAGE]';
        }

        if (($file->file_size ?? 0) > 0 && $file->file_size < 800) {
            $body = 'Very short document body. Only a brief statement is present because the source file is minimal. [LOW_EVIDENCE]';
        }

        if (Str::contains(strtolower((string) $file->original_filename), ['ref', 'reference'], false)) {
            $body .= " References: John Doe (2025), Jane Smith (2024) and https://jpc.edu.ph/reporting-guidelines are cited. [REFERENCE_CITED]";
        }

        return trim($body);
    }

    private function resolvePath(string $disk, string $path): ?string
    {
        try {
            $full = \Storage::disk($disk)->path($path);

            return is_file($full) && is_readable($full) ? $full : null;
        } catch (\Throwable) {
            return null;
        }
    }
}