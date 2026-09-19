<?php

namespace App\Services\AiReports\Ai\Contracts;

/**
 * Immutable completion returned by an {@see AiProvider}.
 */
final class AiCompletion
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly ?string $model = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}
}