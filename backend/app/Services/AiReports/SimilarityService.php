<?php

namespace App\Services\AiReports;

use App\Models\Report;

/**
 * Similarity checking between reports submitted for the same task.
 *
 * Compares the tokenized document fingerprints using the Dice coefficient.
 * This is an advisory indicator for the human reviewer — it is never
 * treated as an automatic plagiarism verdict.
 */
final class SimilarityService
{
    public function compare(Report $report, Report $other): float
    {
        $left = $this->fingerprint($this->textOf($report));
        $right = $this->fingerprint($this->textOf($other));

        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));

        return round(2 * $intersection / (count($left) + count($right)), 4);
    }

    public function status(float $score): string
    {
        if ($score >= (float) config('ai_reports.similarity.highly_similar_threshold', 0.85)) {
            return 'highly_similar';
        }

        if ($score >= (float) config('ai_reports.similarity.similar_threshold', 0.7)) {
            return 'similar';
        }

        return 'normal';
    }

    /**
     * @return list<string>
     */
    private function fingerprint(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        return array_values(array_unique($parts));
    }

    private function textOf(Report $report): string
    {
        try {
            return (new ReportTextExtractor)->extract($report)['text'];
        } catch (\Throwable) {
            return '';
        }
    }
}
