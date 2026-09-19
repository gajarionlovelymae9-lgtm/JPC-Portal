<?php

namespace App\Services\AiReports;

/**
 * Lightweight, deterministic consistency checks over extracted document text.
 * Produces POSSIBLE_INCONSISTENCY findings for the human reviewer — the AI
 * never silently "fixes" a contradiction.
 */
final class ConsistencyChecker
{
    private const LABEL_VALUE = '/\b(total|count|sum|number|students|participants|respondents|employees|teachers|faculty|amount|budget|hours|days|enrollees|attendees|percentage|percent|%)\s*(?:of|for|is|=|:)?\s*\D{0,14}([0-9][0-9.,]*)/i';

    /**
     * Detect numeric contradictions grouped by metric label.
     *
     * @return list<array{category: string, severity: string, finding: string, evidence: string, recommendation: string}>
     */
    public function issues(string $text): array
    {
        $groups = [];

        if (preg_match_all(self::LABEL_VALUE, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) $match[1])));
                $numeric = (string) preg_replace('/[.,]/', '', (string) $match[2]);

                if ($label === '' || ! is_numeric($numeric)) {
                    continue;
                }

                $groups[$label][] = (float) $numeric;
            }
        }

        $issues = [];

        foreach ($groups as $label => $values) {
            if (count($values) < 2 || count(array_unique($values)) === 1) {
                continue;
            }

            $issues[] = [
                'category' => 'POSSIBLE_INCONSISTENCY',
                'severity' => 'medium',
                'finding' => 'The report states conflicting numeric totals for "'.ucfirst($label).'".',
                'evidence' => '"'.ucfirst($label).'" appears with the values '.implode(', ', array_map('strval', $values)).' in the extracted text.',
                'recommendation' => 'Verify the correct '.$label.' and correct the report before submission.',
            ];
        }

        return $issues;
    }
}