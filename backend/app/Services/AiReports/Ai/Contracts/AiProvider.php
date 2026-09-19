<?php

namespace App\Services\AiReports\Ai\Contracts;

/**
 * Pluggable AI engine contract (Phase 3.3-K).
 *
 * Engines translate a bounded system prompt + document data into structured
 * text (strict JSON). Real HTTP integrations are environment-gated; the
 * deterministic fake keeps the pipeline offline and fully testable.
 */
interface AiProvider
{
    /**
     * Stable provider identifier persisted to ai_analyses.provider.
     */
    public function name(): string;

    /**
     * Complete a chat exchange using the given system + user prompts.
     *
     * @throws \App\Services\AiReports\Ai\Exceptions\AiProviderException
     * @throws \App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException
     */
    public function complete(string $system, string $user, AiRequest $request): AiCompletion;
}