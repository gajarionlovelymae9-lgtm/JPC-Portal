<?php

namespace App\Services\AiReports\Ai;

use App\Services\AiReports\Ai\Contracts\AiCompletion;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\ConsistencyChecker;
use App\Services\AiReports\ReferenceAnalyzer;

/**
 * Deterministic offline provider used by tests, local development and as the
 * default when AI_PROVIDER is unset. It parses the marker-bounded prompts
 * produced by {@see AiReportPromptBuilder} and applies the heuristic engine
 * described in the import-hazard rules (token-overlap requirement matching,
 * section-keyword completeness, numeric consistency, reference scan).
 *
 * Its output is canonical: it always satisfies {@see JsonSchemaValidator}, so
 * tests exercise the real schema enforcement path.
 */
final class FakeAiProvider implements AiProvider
{
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'for', 'on', 'with',
        'is', 'are', 'was', 'were', 'as', 'at', 'by', 'it', 'this', 'that',
    ];

    private const SECTION_KEYWORDS = [
        'objective' => ['objective', 'overview', 'purpose', 'introduction', 'description of work'],
        'methodology' => ['methodology', 'method', 'approach', 'conducted', 'work performed'],
        'results' => ['result', 'findings', 'outcome', 'data', 'achieved', 'accomplished', 'accomplishment'],
        'conclusion' => ['conclusion', 'summary', 'overall'],
        'recommendations' => ['recommendation', 'recommend', 'next step', 'follow-up', 'should', 'follow up'],
    ];

    public function name(): string
    {
        return 'fake';
    }

    public function complete(string $system, string $user, AiRequest $request): AiCompletion
    {
        $type = (string) ($request->metadata['analysis_type'] ?? 'validation');
        $text = $this->extractBetween($user, AiReportPromptBuilder::DOC_START, AiReportPromptBuilder::DOC_END);
        $task = $this->extractBetween($user, AiReportPromptBuilder::TASK_START, AiReportPromptBuilder::TASK_END);

        $text = $text !== null ? trim($text) : 'No extractable content was provided.';
        $task = $task !== null ? trim($task) : '';

        $payload = $type === 'summarization'
            ? $this->summarize($text, $task)
            : $this->validate($text, $task, $user);

        return new AiCompletion(
            text: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            provider: $this->name(),
            model: (string) config('ai_reports.ai.model', 'development-sample'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(string $text, string $task): array
    {
        $sentences = $this->sentences($text);
        $raw = $sentences === [] ? ['No extractable content was provided.'] : $sentences;

        $overview = $this->makeOverview($raw);
        $references = $this->themeMatches($text, ['reference', 'citation', 'source', 'url', 'http']);

        $issues = $this->themeMatches($text, ['issue', 'problem', 'concern', 'risk', 'delayed', 'challenge']);
        $actionItems = $this->themeMatches($text, ['submit', 'prepare', 'complete', 'schedule', 'next week', 'tomorrow']);
        $results = $this->themeMatches($text, ['accomplished', 'achieved', 'completed', 'result', 'increase', 'decrease', 'output']);

        return [
            'overview' => $overview,
            'key_activities' => array_slice($raw, 1, 3),
            'key_accomplishments' => $results !== [] ? array_slice($results, 0, 5) : array_slice($raw, 1, 3),
            'results' => $results !== [] ? array_slice($results, 0, 5) : array_slice($raw, 1, 2),
            'issues' => $issues !== [] ? array_slice($issues, 0, 5) : ['No issues identified.'],
            'recommendations' => $actionItems !== [] ? array_slice($actionItems, 0, 5) : array_slice($raw, -1, 1),
            'action_items' => $actionItems !== [] ? array_slice($actionItems, 0, 5) : array_slice($raw, -1, 1),
            'reference_detected' => $references !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(string $text, string $task, string $user): array
    {
        $ocrConfidence = $this->numberFrom($user, 'OCR confidence for this document');
        $anchorPrompted = $this->numberFrom($user, 'Reference anchor score from the local analyzer');
        $scan = (new ReferenceAnalyzer())->scan($text);

        $tokenDoc = $this->tokens($text);
        $tokenTask = $this->tokens($task);
        $requirementDice = $this->dice($tokenDoc, $tokenTask);
        $requirementMatch = $requirementDice >= 0.25
            ? 'matched'
            : ($requirementDice >= 0.10 ? 'partially_matched' : 'not_matched');

        $completeness = $this->completeness($text);
        $evidence = $this->evidence($text, $ocrConfidence);
        $clarity = $this->clarity($text);
        $taskAlignment = round(min(1.0, $requirementDice), 4);
        $relevance = round(min(0.95, $requirementDice + 0.35), 4);
        $anchor = $anchorPrompted ?? (new ReferenceAnalyzer())->analyze($text, new \App\Models\Report())['anchor_score'];
        $overall = round(
            0.25 * $completeness + 0.20 * $evidence + 0.20 * $taskAlignment + 0.20 * $relevance + 0.15 * $clarity,
            4,
        );

        $issues = $this->issues($text, $task, $completeness, $requirementMatch, $requirementDice, $anchor, $ocrConfidence, $evidence);

        $highestSeverity = 0;
        foreach ($issues as $issue) {
            $rank = array_search($issue['severity'], ['low', 'medium', 'high'], true);
            $highestSeverity = max($highestSeverity, is_int($rank) ? $rank + 1 : 0);
        }

        $result = ($overall < 0.5 || $requirementMatch === 'not_matched')
            ? 'fail'
            : (($overall < 0.7 || $highestSeverity >= 2) ? 'warning' : 'pass');

        $explanation = [
            'Completeness is '.round($completeness * 100).'% ('.$this->presentSections($text).' of the expected sections detected).',
            'Requirement alignment is '.ucfirst(str_replace('_', ' ', $requirementMatch)).'.',
            'Detected '.$scan['count'].' reference format(s) in the document.',
        ];

        if ((new PromptInjectionGuard())->containsInstructionOverride($text)) {
            $explanation[] = PromptInjectionGuard::containmentLabel();
        }

        return [
            'task_alignment_score' => $taskAlignment,
            'reference_alignment_score' => round($anchor, 4),
            'reference_anchor_score' => round($anchor, 4),
            'completeness_score' => $completeness,
            'relevance_score' => $relevance,
            'evidence_score' => $evidence,
            'clarity_score' => $clarity,
            'overall_score' => $overall,
            'requirement_match' => $requirementMatch,
            'result' => $result,
            'confidence' => 0.9,
            'explanation' => implode(' ', $explanation),
            'recommendations' => array_slice($this->themeMatches($text, ['correct', 'resubmit', 'add', 'include', 'attach', 'verify', 'provide']), 0, 5),
            'issues' => $issues,
        ];
    }

    /**
     * @return list<array{category: string, severity: string, finding: string, evidence: string, recommendation: string}>
     */
    private function issues(
        string $text,
        string $task,
        float $completeness,
        string $requirementMatch,
        float $requirementDice,
        float $anchor,
        float $ocrConfidence,
        float $evidence,
    ): array {
        $issues = [];

        foreach (['objective', 'methodology', 'results', 'conclusion'] as $section) {
            if (! $this->hasSection($text, $section)) {
                $issues[] = [
                    'category' => 'MISSING_SECTION',
                    'severity' => 'medium',
                    'finding' => 'The report does not clearly contain a '.ucfirst($section).' section.',
                    'evidence' => 'No '.ucfirst($section).' keywords were found in the extracted text.',
                    'recommendation' => 'Add or clearly label the '.ucfirst($section).' section.',
                ];
            }
        }

        if (! $this->hasSection($text, 'recommendations') && trim($task) !== '') {
            $issues[] = [
                'category' => 'MISSING_SECTION',
                'severity' => 'low',
                'finding' => 'No recommendations section detected.',
                'evidence' => 'Recommendation keywords were not found in the extracted text.',
                'recommendation' => 'Consider adding recommended next steps.',
            ];
        }

        $scan = (new ReferenceAnalyzer())->scan($text);
        if ($scan['count'] === 0 && ! $scan['has_heading']) {
            $issues[] = [
                'category' => 'MISSING_REFERENCE',
                'severity' => 'medium',
                'finding' => 'No reference, citation or source URL was detected in the report.',
                'evidence' => 'Reference scanning found no URLs, numbered citations or author-year citations.',
                'recommendation' => 'Add references and citations for the data and sources used.',
            ];
        }

        if ($anchor < 0.45) {
            $issues[] = [
                'category' => 'LOW_REFERENCE_ALIGNMENT',
                'severity' => 'medium',
                'finding' => 'Reference usage does not align with the expected reference count.',
                'evidence' => 'Anchor score '.number_format($anchor, 2).' with '.$scan['count'].' reference(s) detected.',
                'recommendation' => 'Expand the reference list to cover the task specification requirements.',
            ];
        }

        if ($requirementMatch !== 'matched') {
            $issues[] = [
                'category' => 'REQUIREMENT_MISMATCH',
                'severity' => $requirementMatch === 'not_matched' ? 'high' : 'medium',
                'finding' => 'The report content only '.($requirementMatch === 'not_matched' ? 'weakly' : 'partially').' overlaps the task specification.',
                'evidence' => 'Task alignment score '.number_format($requirementDice, 2).'.',
                'recommendation' => 'Re-align the report with the assigned task specification before resubmission.',
            ];
        }

        if ($ocrConfidence < 0.55) {
            $issues[] = [
                'category' => 'OCR_UNCERTAINTY',
                'severity' => 'medium',
                'finding' => 'The extracted document text has low OCR confidence.',
                'evidence' => 'OCR confidence '.number_format($ocrConfidence, 4).'.',
                'recommendation' => 'Re-upload the document in a higher resolution or as a text-based PDF/DOCX.',
            ];
        }

        if ($evidence < 0.5) {
            $issues[] = [
                'category' => 'INSUFFICIENT_EVIDENCE',
                'severity' => 'low',
                'finding' => 'The document contains little extractable evidence to support validation.',
                'evidence' => 'Evidence score '.number_format($evidence, 2).' based on extracted text length and OCR confidence.',
                'recommendation' => 'Provide a more detailed report or a text-based document.',
            ];
        }

        foreach ((new ConsistencyChecker())->issues($text) as $consistencyIssue) {
            $issues[] = $consistencyIssue;
        }

        return array_slice($issues, 0, 12);
    }

    private function completeness(string $text): float
    {
        $present = 0;
        foreach (array_keys(self::SECTION_KEYWORDS) as $section) {
            if ($this->hasSection($text, $section)) {
                $present++;
            }
        }

        return round($present / count(self::SECTION_KEYWORDS), 4);
    }

    private function presentSections(string $text): string
    {
        $present = [];
        foreach (array_keys(self::SECTION_KEYWORDS) as $section) {
            if ($this->hasSection($text, $section)) {
                $present[] = $section;
            }
        }

        return $present === [] ? 'none' : implode(', ', $present);
    }

    private function hasSection(string $text, string $section): bool
    {
        foreach (self::SECTION_KEYWORDS[$section] as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function evidence(string $text, float $ocrConfidence): float
    {
        $lengthFair = mb_strlen($text) >= 300 ? 1.0 : (mb_strlen($text) >= 150 ? 0.65 : 0.35);

        return round(min(1.0, 0.45 * $lengthFair + 0.55 * $ocrConfidence), 4);
    }

    private function clarity(string $text): float
    {
        $sentences = $this->sentences($text);
        if (count($sentences) < 3) {
            return 0.55;
        }

        return round(min(0.97, 0.75 + min(0.25, 0.05 * count($sentences))), 4);
    }

    /**
     * @param  list<string>  $sentences
     */
    private function makeOverview(array $sentences): string
    {
        $pick = array_slice($sentences, 0, 2);

        return implode(' ', array_map('trim', $pick));
    }

    /**
     * @return list<string>
     */
    private function themeMatches(string $text, array $keywords): array
    {
        $hits = [];

        foreach ($this->sentences($text) as $sentence) {
            if ($sentence === '') {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (stripos($sentence, $keyword) !== false) {
                    $hits[] = trim($sentence);
                    break;
                }
            }
        }

        return array_slice($hits, 0, 8);
    }

    /**
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\R+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        return array_map(fn (string $part): string => trim($part), $parts);
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        return array_values(array_unique(array_diff($parts, self::STOPWORDS)));
    }

    private function dice(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = array_intersect($a, $b);

        return round(2 * count($intersection) / (count($a) + count($b)), 4);
    }

    private function extractBetween(string $haystack, string $start, string $end): ?string
    {
        $startPos = strpos($haystack, $start);

        if ($startPos === false) {
            return null;
        }

        $endPos = strpos($haystack, $end, $startPos + strlen($start));

        if ($endPos === false) {
            return null;
        }

        return substr($haystack, $startPos + strlen($start), $endPos - $startPos - strlen($start));
    }

    private function numberFrom(string $text, string $label): ?float
    {
        if (preg_match('/' . preg_quote($label, '/') . ': ([\d.]+)/', $text, $matches) === 1) {
            return (float) $matches[1];
        }

        return null;
    }
}