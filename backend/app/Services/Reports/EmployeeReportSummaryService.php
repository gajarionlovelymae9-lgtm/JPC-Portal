<?php

namespace App\Services\Reports;

use App\Models\AiAnalysis;
use App\Models\AiSummary;
use App\Models\Report;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a grounded AI summary for an employee-submitted report.
 *
 * The summary is derived only from the report's actual content. If the AI
 * provider is unavailable or fails, the report submission must still succeed
 * and the summary status is recorded as unavailable/failed (never fabricated).
 */
class EmployeeReportSummaryService
{
    public function __construct(private readonly AiProvider $provider) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(Report $report, bool $regenerate = false): array
    {
        $report->loadMissing(['task', 'employee', 'aiSummaries']);

        $currentStatus = $report->ai_summary_status;

        if (! $regenerate && $currentStatus === 'generated' && $report->ai_summary) {
            return $this->result($report, 'generated', $this->decodeSummary($report->ai_summary));
        }

        $sourceText = $this->buildSourceText($report);
        $language = $this->resolveLanguage($report);

        if (trim($sourceText) === '') {
            return $this->persistUnavailable($report, 'No report content is available to summarize.', 'unavailable', $language);
        }

        try {
            $request = new AiRequest(
                model: (string) (config('ai_reports.ai.model') ?? config('ai.model', 'development-sample')),
                maxTokens: 1200,
                temperature: 0.1,
                expectJson: true,
                metadata: ['analysis_type' => 'summarization', 'scope' => 'employee_report', 'language' => $language],
            );

            $completion = $this->provider->complete(
                $this->systemPrompt($language),
                $this->userPrompt($report, $sourceText, $language),
                $request,
            );

            $payload = $this->normalizePayload($completion->text, $report, $language);

            return $this->persistSummary($report, $payload, $completion->provider, $completion->model, $regenerate);
        } catch (Throwable $e) {
            Log::warning('Employee report AI summary generation failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            $status = $this->isUnavailableError($e) ? 'unavailable' : 'failed';

            return $this->persistUnavailable($report, $e->getMessage(), $status, $language);
        }
    }

    private function systemPrompt(string $language = 'english'): string
    {
        $languageInstruction = match ($this->normalizeLanguage($language)) {
            'filipino' => 'Isulat ang lahat ng value (string at array item) sa malinaw at propesyonal na Filipino/Tagalog. Panatilihin ang eksaktong kahulugan ng impormasyon mula sa report.',
            'taglish' => 'Isulat ang lahat ng value (string at array item) sa natural at propesyonal na Taglish. Panatilihin ang eksaktong meaning ng information mula sa report.',
            default => 'Write all values (strings and array items) in clear, professional English.',
        };

        return <<<PROMPT
You summarize employee work reports for an internal performance system.
Rules:
- Use ONLY facts present in the report content.
- Do not invent accomplishments, numbers, dates, completed tasks, or outcomes.
- If a section has no information in the report, use a clear "not provided" statement in the same language as the summary values.
- Keep the summary clear, professional, and concise.
- Preserve the actual meaning of employee-provided information. Do not add or remove facts.
- {$languageInstruction}
- Return valid JSON only with these exact English keys:
  executive_summary (string),
  accomplishments (array of strings),
  completed_tasks (array of strings),
  challenges (array of strings),
  recommendations (array of strings),
  next_steps (array of strings).
PROMPT;
    }

    private function userPrompt(Report $report, string $sourceText, string $language = 'english'): string
    {
        $meta = array_filter([
            'Title' => $report->title,
            'Report type' => $report->report_type,
            'Reporting period' => $report->reporting_period,
            'Task' => $report->task?->title,
            'Employee' => $report->employee?->full_name,
            'Status' => is_object($report->status) ? $report->status->value : $report->status,
        ], fn ($value) => $value !== null && $value !== '');

        $lines = [];
        foreach ($meta as $label => $value) {
            $lines[] = $label.': '.$value;
        }
        $lines[] = '';
        $lines[] = 'Preferred summary language: '.$this->normalizeLanguage($language).'.';
        $lines[] = '';
        $lines[] = 'Report content:';
        $lines[] = $sourceText;

        return implode("\n", $lines);
    }

    private function buildSourceText(Report $report): string
    {
        $parts = [];

        $this->appendSection($parts, 'Report Description', $report->content ?? $report->description);
        $this->appendSection($parts, 'Accomplishments', $report->accomplishments);
        $this->appendSection($parts, 'Completed Tasks', $report->completed_tasks_summary);
        $this->appendSection($parts, 'Challenges Encountered', $report->challenges_encountered);
        $this->appendSection($parts, 'Next Plans', $report->next_plans);
        $this->appendSection($parts, 'References', $report->references ?? $report->remarks);

        return trim(implode("\n\n", $parts));
    }

    /**
     * @param  list<string>  $parts
     */
    private function appendSection(array &$parts, string $label, mixed $value): void
    {
        $text = is_string($value) ? trim(strip_tags($value)) : '';

        if ($text !== '') {
            $parts[] = $label.":\n".$text;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(string $text, Report $report, string $language = 'english'): array
    {
        $decoded = $this->extractJson($text);
        $lang = $this->normalizeLanguage($language);

        $executive = $this->stringValue(
            $decoded['executive_summary'] ?? $decoded['overview'] ?? $decoded['summary'] ?? null
        );

        $accomplishments = $this->listValue(
            $decoded['accomplishments'] ?? $decoded['key_accomplishments'] ?? $decoded['results'] ?? null
        );
        $completedTasks = $this->listValue(
            $decoded['completed_tasks'] ?? $decoded['key_activities'] ?? null
        );
        $challenges = $this->listValue(
            $decoded['challenges'] ?? $decoded['issues'] ?? null
        );
        $recommendations = $this->listValue($decoded['recommendations'] ?? null);
        $nextSteps = $this->listValue(
            $decoded['next_steps'] ?? $decoded['action_items'] ?? null
        );

        if ($executive === '' && $accomplishments === [] && $completedTasks === [] && $challenges === []) {
            // Last-resort grounded fallback from the actual report fields (no invention).
            $executive = $this->fallbackExecutiveSummary($report);
            $accomplishments = $this->fallbackList($report->accomplishments);
            $completedTasks = $this->fallbackList($report->completed_tasks_summary ?? $report->completed_tasks ?? null);
            $challenges = $this->fallbackList($report->challenges_encountered);
            $nextSteps = $this->fallbackList($report->next_plans);
        }

        $notProvided = $this->t($lang, 'not_provided');
        $noChallenges = $this->t($lang, 'no_challenges');
        $reviewAdvice = $this->t($lang, 'review_advice');
        $disclaimer = $this->t($lang, 'disclaimer');

        return [
            'executive_summary' => $executive !== '' ? $executive : $notProvided,
            'accomplishments' => $accomplishments !== [] ? $accomplishments : [$notProvided],
            'completed_tasks' => $completedTasks !== [] ? $completedTasks : [$notProvided],
            'challenges' => $challenges !== [] ? $challenges : [$noChallenges],
            'recommendations' => $recommendations !== [] ? $recommendations : [$reviewAdvice],
            'next_steps' => $nextSteps !== [] ? $nextSteps : [$notProvided],
            'generated_from' => 'employee_report_submission',
            'disclaimer' => $disclaimer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJson(string $text): array
    {
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['executive_summary' => $text];
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $parts = array_values(array_filter(array_map(fn ($item) => $this->stringValue($item), $value), fn ($item) => $item !== ''));

            return trim(implode(' ', $parts));
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n|•|\- /', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = $this->stringValue($item);
            $text = trim(preg_replace('/^\s*[\-\*\d\.\)]+\s*/', '', $text) ?? $text);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    private function fallbackExecutiveSummary(Report $report): string
    {
        $parts = array_values(array_filter([
            $this->firstSentence($report->accomplishments),
            $this->firstSentence($report->completed_tasks_summary),
            $this->firstSentence($report->challenges_encountered),
            $this->firstSentence($report->content ?? $report->description),
        ], fn ($value) => $value !== ''));

        return $parts === [] ? '' : implode(' ', array_slice($parts, 0, 3));
    }

    /**
     * @return list<string>
     */
    private function fallbackList(mixed $value): array
    {
        return $this->listValue($value);
    }

    private function firstSentence(mixed $value): string
    {
        $text = is_string($value) ? trim(strip_tags($value)) : '';

        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(?<=[.!?])\s+/', $text, 2);

        return trim($parts[0] ?? $text);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function persistSummary(Report $report, array $payload, string $provider, ?string $model, bool $regenerate): array
    {
        $analysis = $this->createAnalysis($report, $provider, $model, 'completed');

        $summary = new AiSummary;
        $summary->report_id = $report->id;
        $summary->ai_analysis_id = $analysis->id;
        $summary->forceFill([
            'summary' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'key_accomplishments' => $payload['accomplishments'] ?? [],
            'key_activities' => $payload['completed_tasks'] ?? [],
            'key_findings' => $payload['completed_tasks'] ?? [],
            'results' => $payload['accomplishments'] ?? [],
            'issues' => $payload['challenges'] ?? [],
            'recommendations' => $payload['recommendations'] ?? [],
            'action_items' => $payload['next_steps'] ?? [],
            'generated_at' => now(),
        ])->save();

        $report->forceFill([
            'ai_summary' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ai_summary_status' => 'generated',
            'ai_summary_generated_at' => now(),
            'ai_status' => 'completed',
        ])->save();

        return $this->result($report->fresh(), 'generated', $payload, $regenerate);
    }

    /**
     * @return array<string, mixed>
     */
    private function persistUnavailable(Report $report, string $reason, string $status = 'unavailable', string $language = 'english'): array
    {
        $analysis = $this->createAnalysis($report, $this->provider->name(), config('ai_reports.ai.model') ?? config('ai.model'), $status === 'failed' ? 'failed' : 'completed', $reason);
        $lang = $this->normalizeLanguage($language);

        $report->forceFill([
            'ai_summary_status' => $status,
            'ai_status' => $status,
        ])->save();

        return $this->result($report->fresh(), $status, [
            'executive_summary' => null,
            'accomplishments' => [],
            'completed_tasks' => [],
            'challenges' => [],
            'recommendations' => [],
            'next_steps' => [],
            'message' => $status === 'unavailable'
                ? $this->t($lang, 'unavailable')
                : $this->t($lang, 'failed'),
            'disclaimer' => $this->t($lang, 'advisory'),
        ], false, $reason);
    }

    private function createAnalysis(Report $report, string $provider, ?string $model, string $status, ?string $error = null): AiAnalysis
    {
        $analysis = new AiAnalysis;
        $analysis->fill([
            'report_id' => $report->id,
            'report_version_id' => $report->versions()->latest('id')->value('id'),
            'analysis_type' => 'employee_report_summary',
            'provider' => $provider,
            'model' => $model,
            'analysis_version' => (string) config('ai_reports.analysis.analysis_version', 'employee-v1'),
        ]);
        $analysis->forceFill([
            'status' => $status,
            'input_hash' => hash('sha256', $this->buildSourceText($report)),
            'started_at' => now(),
            'completed_at' => now(),
            'error_message' => $error ? mb_substr($error, 0, 1000) : null,
        ])->save();

        return $analysis;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function result(Report $report, string $status, ?array $payload, bool $regenerated = false, ?string $message = null): array
    {
        return [
            'report_id' => $report->id,
            'ai_summary_status' => $status,
            'ai_summary_generated_at' => optional($report->ai_summary_generated_at)->toIso8601String(),
            'summary' => $payload,
            'regenerated' => $regenerated,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSummary(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : ['executive_summary' => $value];
        }

        return [];
    }

    private function isUnavailableError(Throwable $e): bool
    {
        $name = class_basename($e);
        $message = strtolower($e->getMessage());

        return str_contains($name, 'Configuration')
            || str_contains($message, 'not configured')
            || str_contains($message, 'api key')
            || str_contains($message, 'missing');
    }

    private function resolveLanguage(Report $report): string
    {
        $preference = null;

        try {
            $report->loadMissing(['employee.user', 'user']);
        } catch (Throwable) {
            // Relations may not exist in all deployments; fall back safely.
        }

        $preference = $report->employee?->user?->ai_language_preference
            ?? $report->user?->ai_language_preference
            ?? $report->employee?->ai_language_preference
            ?? null;

        $lang = $this->normalizeLanguage(is_string($preference) ? $preference : null);

        return $lang === 'auto' ? 'english' : $lang;
    }

    private function normalizeLanguage(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === 'tagalog') {
            $value = 'filipino';
        }

        return in_array($value, ['auto', 'english', 'filipino', 'taglish'], true) ? $value : 'auto';
    }

    private function t(string $language, string $key): string
    {
        $language = $this->normalizeLanguage($language);
        if ($language === 'auto') {
            $language = 'english';
        }

        $messages = [
            'not_provided' => [
                'english' => 'Not provided in the submitted report.',
                'filipino' => 'Hindi ibinigay sa isinumiteng report.',
                'taglish' => 'Hindi provided sa submitted report.',
            ],
            'no_challenges' => [
                'english' => 'No challenges were reported.',
                'filipino' => 'Walang naiulat na hamon.',
                'taglish' => 'Walang na-report na challenges.',
            ],
            'review_advice' => [
                'english' => 'Review the submitted report and provide follow-up guidance if needed.',
                'filipino' => 'Suriin ang isinumiteng report at magbigay ng karagdagang gabay kung kailangan.',
                'taglish' => 'I-review ang submitted report at magbigay ng follow-up guidance kung kailangan.',
            ],
            'disclaimer' => [
                'english' => 'AI-generated summary based on the submitted report. Verify against the original report before making official decisions.',
                'filipino' => 'AI-generated na buod batay sa isinumiteng report. I-verify ito laban sa orihinal na report bago gumawa ng opisyal na desisyon.',
                'taglish' => 'AI-generated summary batay sa submitted report. I-verify ito laban sa original report bago gumawa ng official decisions.',
            ],
            'unavailable' => [
                'english' => 'AI summary is unavailable because no AI provider is configured.',
                'filipino' => 'Hindi available ang AI summary dahil walang naka-configure na AI provider.',
                'taglish' => 'Hindi available ang AI summary dahil walang configured na AI provider.',
            ],
            'failed' => [
                'english' => 'AI summary could not be generated at this time. You can try generating it again later.',
                'filipino' => 'Hindi magawa ang AI summary sa ngayon. Maaari mong subukang muli mamaya.',
                'taglish' => 'Hindi ma-generate ang AI summary ngayon. Puwede mong subukan ulit mamaya.',
            ],
            'advisory' => [
                'english' => 'AI-generated summary is advisory and only appears when available.',
                'filipino' => 'Ang AI-generated na buod ay gabay lamang at lilitaw lang kapag available.',
                'taglish' => 'Ang AI-generated summary ay advisory lang at lalabas lang kapag available.',
            ],
        ];

        return $messages[$key][$language] ?? $messages[$key]['english'] ?? $key;
    }
}
