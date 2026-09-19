<?php

namespace App\Enums;

/**
 * DataQualityIssueSeverity — Phase 8 severity classification (spec item 5).
 *
 * Ordered LOW < MEDIUM < HIGH < CRITICAL. Severity weights are centralized here
 * so no rule hardcodes its own ordering arithmetic, and `score()` feeds the
 * Data Quality Score computation (spec items 35, 45).
 */
enum DataQualityIssueSeverity: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    /**
     * Numeric weight used for sorting and for the weighted data-quality score.
     * Higher weight = more severe. Not part of any performance/KPI calc.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /**
     * Color token mapped to the existing design system (tailwind classes used
     * by the React frontend sever-badge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'yellow',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }
}
