<?php

namespace App\Services\AiReports\Ocr;

use App\Models\ReportFile;
use App\Services\AiReports\Ocr\Contracts\OcrProvider;
use App\Services\AiReports\Ocr\Contracts\OcrResult;
use App\Services\AiReports\Ocr\Exceptions\OcrConfigurationException;
use App\Services\AiReports\Ocr\Exceptions\OcrProcessingException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Str;

/**
 * Google Cloud Vision OCR integration (REST annotateImage).
 *
 * Supported out of the box:
 *   - JPG/JPEG/PNG images  -> synchronous `images:annotate` (base64 content),
 *                             requires GOOGLE_VISION_API_KEY.
 *   - DOCX                 -> embedded text extraction (no Vision round-trip),
 *                             image-page rendering is only possible via the
 *                             async GCS flow.
 *   - PDF                  -> embedded text extraction when available; scanned
 *                             PDFs require the asyncBatchAnnotateFiles flow on
 *                             Google Cloud Storage, which is documented in
 *                             docs/ai/phase-3-3-k — never attempted here.
 *
 * Nothing is ever logged or persisted besides the extracted text and score.
 */
class GoogleVisionOcrService implements OcrProvider
{
    private const SYNC_IMAGE_BYTE_LIMIT = 9_000_000;

    public function __construct(
        protected HttpClient $http,
        protected EmbeddedTextExtractor $extractor,
    ) {}

    public function name(): string
    {
        return 'google_vision';
    }

    public function extract(ReportFile $file): OcrResult
    {
        $lower = strtolower((string) $file->original_filename);

        if (str_ends_with($lower, '.docx') || str_ends_with($lower, '.pdf')) {
            $embedded = $this->embeddedText($file);

            if ($embedded !== null) {
                return new OcrResult($embedded, 0.97, $this->name(), 'embedded');
            }

            throw new OcrConfigurationException(
                str_ends_with($lower, '.pdf')
                    ? 'Scanned PDFs require the Google Cloud Storage async flow; splitting the document into images is currently required for Vision OCR.'
                    : 'DOCX without embedded text must be converted to images before Vision OCR can extract it.',
            );
        }

        if (! str_ends_with($lower, '.png') && ! str_ends_with($lower, '.jpg') && ! str_ends_with($lower, '.jpeg')) {
            throw new OcrProcessingException('Unsupported document type for Vision OCR: '.$file->mime_type);
        }

        return $this->annotateImage($file);
    }

    private function annotateImage(ReportFile $file): OcrResult
    {
        $apiKey = (string) config('ai_reports.ocr.google.api_key', '');

        if ($apiKey === '') {
            throw new OcrConfigurationException(
                'Google Vision OCR requires GOOGLE_VISION_API_KEY. Use the fake provider (default) until credentials are configured.',
            );
        }

        $disk = $file->storage_disk ?: config('ai_reports.storage.disk', 'local');
        $bytes = \Storage::disk($disk)->get($file->file_path);

        if ($bytes === null) {
            throw new OcrProcessingException('Report document is missing from storage.');
        }

        if (strlen($bytes) > self::SYNC_IMAGE_BYTE_LIMIT) {
            throw new OcrConfigurationException(
                'Image exceeds the 9MB synchronous Vision limit; compress it or use the async GCS flow.',
            );
        }

        $endpoint = rtrim((string) config('ai_reports.ocr.google.endpoint', 'https://vision.googleapis.com/v1'), '/');

        $languageHints = array_filter([
            (string) config('ai_reports.ocr.language', 'en'),
        ]);

        $response = $this->http
            ->timeout(120)
            ->withHeaders(['Accept' => 'application/json'])
            ->post($endpoint.'/images:annotate', [
                'requests' => [[
                    'image' => ['content' => base64_encode($bytes)],
                    'features' => [
                        ['type' => config('ai_reports.ocr.google.features.0', 'TEXT_DETECTION')],
                    ],
                    'imageContext' => ['languageHints' => $languageHints],
                ]],
            ], ['query' => ['key' => $apiKey]]);

        if ($response->failed()) {
            throw new OcrProcessingException(
                'Google Vision request failed with HTTP '.$response->status().'.',
            );
        }

        $payload = $response->json('responses.0', []);

        if (isset($payload['error']) && is_array($payload['error'])) {
            throw new OcrProcessingException('Google Vision returned an error for the document.');
        }

        $text = (string) (data_get($payload, 'fullTextAnnotation.text') ?? '');

        if (trim($text) === '') {
            throw new OcrProcessingException('Google Vision did not detect any text in the document.');
        }

        return new OcrResult(
            rawText: $text,
            confidence: $this->confidence($payload),
            provider: $this->name(),
            source: 'google_vision',
            language: (string) config('ai_reports.ocr.language', 'en'),
        );
    }

    /**
     * Weighted average of word-level confidence when Vision supplies it,
     * otherwise a conservative default. Vision does not always return a
     * page-level confidence for TEXT_DETECTION.
     */
    private function confidence(array $payload): float
    {
        $words = data_get($payload, 'fullTextAnnotation.pages.0.blocks.*.paragraphs.*.words.*', []);

        if (! is_array($words) || $words === []) {
            return 0.9;
        }

        $total = 0.0;
        $weight = 0;

        foreach ($words as $word) {
            $c = (float) data_get($word, 'confidence');

            if ($c > 0) {
                $total += $c;
                $weight++;
            }
        }

        return $weight > 0 ? round($total / $weight, 4) : 0.9;
    }

    private function embeddedText(ReportFile $file): ?string
    {
        $disk = $file->storage_disk ?: config('ai_reports.storage.disk', 'local');
        $fullPath = \Storage::disk($disk)->path($file->file_path);

        $lower = strtolower((string) $file->original_filename);
        $text = str_ends_with($lower, '.docx')
            ? $this->extractor->fromDocx($fullPath)
            : $this->extractor->fromPdf($fullPath);

        return $text !== null && trim(Str::squish($text)) !== '' ? $text : null;
    }
}