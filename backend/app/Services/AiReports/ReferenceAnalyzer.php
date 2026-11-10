<?php

namespace App\Services\AiReports;

use App\Models\Report;

/**
 * Reference/anchor analysis for uploaded reports.
 *
 * The system never verifies the cited sources against the internet — the
 * spec requires the honest label "Reference format detected; source
 * verification unavailable." The anchor score therefore reflects how much of
 * the document uses a formal, recognizable reference format (URLs, numbered
 * citations or author-year) relative to the references that were expected.
 */
final class ReferenceAnalyzer
{
    private const URL = '/https?:\/\/[^\s<>"\']+/i';

    private const NUMBERED_CITATION = '/\[([0-9]+(?:\s*,\s*[0-9]+)*)\]/i';

    private const AUTHOR_YEAR = '/\(([A-Z][A-Za-z\'’\-]+)(?:\s+et\s+al\.?)?,\s*(?:19|20)\d{2}\s*\)/i';

    /**
     * Scan raw text for reference-like markers.
     *
     * @return array{urls: list<string>, citations: list<string>, count: int, has_heading: bool}
     */
    public function scan(string $text): array
    {
        $urls = [];
        preg_match_all(self::URL, $text, $urlMatches);
        foreach (($urlMatches[0] ?? []) as $url) {
            $urls[trim(rtrim((string) $url, '.,;'))] = true;
        }

        $citations = [];
        preg_match_all(self::NUMBERED_CITATION, $text, $numberMatches);
        foreach (($numberMatches[0] ?? []) as $hit) {
            $citations[trim((string) $hit)] = true;
        }

        preg_match_all(self::AUTHOR_YEAR, $text, $authorMatches);
        foreach (($authorMatches[0] ?? []) as $hit) {
            $citations[trim((string) $hit)] = true;
        }

        $hasHeading = preg_match('/\b(?:references|bibliography|works cited|sources)\b/i', $text) === 1;

        return [
            'urls' => array_keys($urls),
            'citations' => array_keys($citations),
            'count' => count($urls) + count($citations),
            'has_heading' => $hasHeading,
        ];
    }

    /**
     * Compute the anchor score and its explanation for a report.
     *
     * @return array{anchor_score: float, label: string, detected: int, expected: int, urls: list<string>}
     */
    public function analyze(string $text, Report $report): array
    {
        $scan = $this->scan($text);

        $expected = $report->references()->count()
            + (int) ($report->task?->references()->count() ?? 0);

        $anchor = (float) config('ai_reports.analysis.reference.anchor_default', 0.35);

        if ($scan['has_heading']) {
            $anchor = 0.9;
        } elseif ($scan['count'] > 0) {
            $anchor = 0.55;
        }

        if ($expected > 0 && $scan['count'] >= $expected) {
            $anchor = max($anchor, 0.85);
        }

        if ($scan['urls'] !== []) {
            $anchor = min(1.0, $anchor + 0.1);
        }

        $label = $scan['count'] === 0 && ! $scan['has_heading']
            ? 'No references detected.'
            : (string) config('ai_reports.analysis.reference.unverified_label', 'Reference format detected; source verification unavailable.');

        return [
            'anchor_score' => round(min(1.0, max(0.0, $anchor)), 4),
            'label' => $label,
            'detected' => $scan['count'],
            'expected' => $expected,
            'urls' => $scan['urls'],
        ];
    }
}
