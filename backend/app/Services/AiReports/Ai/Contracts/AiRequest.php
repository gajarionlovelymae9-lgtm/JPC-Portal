<?php

namespace App\Services\AiReports\Ai\Contracts;

/**
 * Parameters for a single completion call.
 */
final class AiRequest
{
    public function __construct(
        public readonly string $model,
        public readonly int $maxTokens,
        public readonly float $temperature = 0.1,
        public readonly bool $expectJson = true,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}
}
