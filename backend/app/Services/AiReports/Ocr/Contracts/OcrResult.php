<?php

namespace App\Services\AiReports\Ocr\Contracts;

/**
 * Immutable OCR extraction result produced by an {@see OcrProvider}.
 */
final class OcrResult
{
    public function __construct(
        public readonly string $rawText,
        public readonly float $confidence,
        public readonly string $provider,
        public readonly string $source,
        public readonly ?string $language = null,
    ) {}

    /**
     * Whether the extracted text is usable by the AI pipeline.
     */
    public function hasText(): bool
    {
        return trim($this->rawText) !== '';
    }
}