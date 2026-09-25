<?php

namespace App\Services\DataQuality;

class DataQualityScoreService
{
    /**
     * Calculates the overall health score from 0.00% to 100.00%.
     * High deductions for critical and high issues, lower for medium/low.
     */
    public function calculateScore(int $critical, int $high, int $medium, int $low, int $resolved): float
    {
        $penalty = ($critical * 12.0) + ($high * 5.0) + ($medium * 2.0) + ($low * 0.5);

        // Resolution bonus slightly mitigates deductions
        $bonus = min($resolved * 0.5, 15.0);

        $score = 100.0 - $penalty + $bonus;

        return round(max(0.0, min(100.0, $score)), 1);
    }
}
