<?php

namespace App\Services\AiReports\Ai;

use App\Services\AiReports\Ai\Exceptions\AiOutputException;

/**
 * Strict JSON validator for AI-generated report analysis output.
 *
 * Enforces the exact shape the pipeline persists: required keys present,
 * numerically sane scores (0.00-1.00), and issue records limited to the
 * known category/severity vocabularies. Anything unexpected rejects the whole
 * payload — the pipeline prefers an explicit AI_ANALYSIS_FAILED over
 * displaying model output the schema cannot trust.
 */
final class JsonSchemaValidator
{
    public const ISSUE_CATEGORIES = [
        'MISSING_SECTION',
        'MISSING_REFERENCE',
        'REQUIREMENT_MISMATCH',
        'POSSIBLE_INCONSISTENCY',
        'INSUFFICIENT_EVIDENCE',
        'LOW_REFERENCE_ALIGNMENT',
        'OCR_UNCERTAINTY',
    ];

    public const REQUIREMENT_MATCHES = ['matched', 'partially_matched', 'not_matched'];

    public const RESULTS = ['pass', 'warning', 'fail'];

    public const SEVERITIES = ['low', 'medium', 'high'];

    private const SUMMARY_KEYS = [
        'overview',
        'key_activities',
        'key_accomplishments',
        'results',
        'issues',
        'recommendations',
        'action_items',
    ];

    private const VALIDATION_SCORE_KEYS = [
        'task_alignment_score',
        'reference_alignment_score',
        'reference_anchor_score',
        'completeness_score',
        'relevance_score',
        'evidence_score',
        'clarity_score',
        'overall_score',
    ];

    /**
     * Decode a JSON payload into an associative array.
     *
     * @return array<string, mixed>
     *
     * @throws AiOutputException
     */
    public function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AiOutputException('AI output is not valid JSON: '.$e->getMessage());
        }

        if (! is_array($decoded) || $decoded === []) {
            throw new AiOutputException('AI output must be a non-empty JSON object.');
        }

        return $decoded;
    }

    /**
     * Validate and normalize a summary payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws AiOutputException
     */
    public function summary(array $data): array
    {
        $overview = $this->requiredString($data, 'overview');

        return [
            'overview' => $overview,
            'key_activities' => $this->stringList($data, 'key_activities'),
            'key_accomplishments' => $this->stringList($data, 'key_accomplishments'),
            'results' => $this->stringList($data, 'results'),
            'issues' => $this->stringList($data, 'issues'),
            'recommendations' => $this->stringList($data, 'recommendations'),
            'action_items' => $this->stringList($data, 'action_items'),
        ];
    }

    /**
     * Validate and normalize a validation payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws AiOutputException
     */
    public function validation(array $data): array
    {
        $scores = [];

        foreach (self::VALIDATION_SCORE_KEYS as $key) {
            $scores[$key] = $this->score($key, $data[$key] ?? null);
        }

        $requirementMatch = $this->enumValue($data['requirement_match'] ?? null, self::REQUIREMENT_MATCHES, 'requirement_match');
        $result = $this->enumValue($data['result'] ?? null, self::RESULTS, 'result');
        $confidence = $this->score('confidence', $data['confidence'] ?? null);

        return [
            'scores' => $scores,
            'requirement_match' => $requirementMatch,
            'result' => $result,
            'confidence' => $confidence,
            'explanation' => $this->nullableString($data, 'explanation') ?? '',
            'recommendations' => $this->stringList($data, 'recommendations'),
            'issues' => $this->issues($data['issues'] ?? []),
        ];
    }

    /**
     * @return list<array{category: string, severity: string, finding: string, evidence: string, recommendation: string}>
     *
     * @throws AiOutputException
     */
    private function issues(mixed $value): array
    {
        if (! is_array($value)) {
            throw new AiOutputException('AI output field "issues" must be a list.');
        }

        $issues = [];

        foreach ($value as $index => $issue) {
            if (! is_array($issue)) {
                throw new AiOutputException("AI output issue #{$index} must be an object.");
            }

            $category = $this->enumValue($issue['category'] ?? null, self::ISSUE_CATEGORIES, "issues.{$index}.category");
            $severity = $this->enumValue($issue['severity'] ?? null, self::SEVERITIES, "issues.{$index}.severity");
            $finding = $this->requiredString($issue, 'finding', "issues.{$index}");

            $issues[] = [
                'category' => $category,
                'severity' => $severity,
                'finding' => $finding,
                'evidence' => $this->nullableString($issue, 'evidence') ?? '',
                'recommendation' => $this->nullableString($issue, 'recommendation') ?? '',
            ];
        }

        return array_slice($issues, 0, 12);
    }

    private function score(string $key, mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new AiOutputException("AI output field \"{$key}\" must be numeric.");
        }

        $score = (float) $value;

        if ($score < 0.0 || $score > 1.0) {
            throw new AiOutputException("AI output field \"{$key}\" must be between 0.00 and 1.00.");
        }

        return round($score, 4);
    }

    private function requiredString(array $data, string $key, ?string $path = null): string
    {
        $value = $data[$key] ?? null;
        $label = $path ?? "\"{$key}\"";

        if (! is_string($value) || trim($value) === '') {
            throw new AiOutputException("AI output field {$label} must be a non-empty string.");
        }

        return trim($value);
    }

    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return is_string($value) ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return array_slice($list, 0, 20);
    }

    private function enumValue(mixed $value, array $allowed, string $field): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new AiOutputException("AI output field \"{$field}\" must be one of: ".implode(', ', $allowed).'.');
        }

        return $value;
    }
}
