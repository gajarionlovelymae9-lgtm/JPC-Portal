<?php

namespace App\Services\AiReports\Ai;

use App\Services\AiReports\Ai\Contracts\AiCompletion;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Services\AiReports\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\Factory as HttpClient;

/**
 * OpenAI-compatible HTTP provider (chat/completions).
 *
 * Backed by the configured AI_ENDPOINT/AI_API_KEY. Responses must already be
 * plain JSON content (no JSON-mode code fences); the schema validator is the
 * single source of truth regardless.
 */
final class HttpAiProvider implements AiProvider
{
    public function __construct(private readonly HttpClient $http) {}

    public function name(): string
    {
        return (string) config('ai_reports.ai.provider', 'openai');
    }

    public function complete(string $system, string $user, AiRequest $request): AiCompletion
    {
        $endpoint = rtrim((string) config('ai_reports.ai.http.endpoint'), '/');

        if ($endpoint === '') {
            throw new AiProviderConfigurationException('AI provider endpoint is not configured (ai_reports.ai.http.endpoint).');
        }

        $apiKey = (string) config('ai_reports.ai.http.api_key', '');
        $token = (string) config('ai_reports.ai.http.token', '');

        if (str_contains($endpoint, 'openai') && $apiKey === '') {
            throw new AiProviderConfigurationException('An API key is required for the configured OpenAI-compatible endpoint.');
        }

        try {
            $response = $this->http->acceptJson()
                ->when($token !== '', fn ($client) => $client->withToken($token))
                ->when($apiKey !== '', fn ($client) => $client->withToken($apiKey))
                ->timeout((int) config('ai_reports.ai.http.timeout', 60))
                ->post($endpoint, [
                    'model' => $request->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => $request->temperature,
                    'max_tokens' => $request->maxTokens,
                    'response_format' => $request->expectJson ? ['type' => 'json_object'] : null,
                ]);
        } catch (\Throwable $e) {
            throw new AiProviderException('AI provider request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new AiProviderException('AI provider returned HTTP '.$response->status().': '.substr($response->body(), 0, 300));
        }

        $decoded = $response->json();
        $content = data_get($decoded, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiProviderException('AI provider returned an empty completion.');
        }

        return new AiCompletion(
            text: trim($content),
            provider: $this->name(),
            model: $request->model,
            metadata: ['status' => $response->status()],
        );
    }
}
