<?php

namespace App\Enums;

enum PerformanceRating: string
{
    case Excellent = 'Excellent';
    case VeryGood = 'Very Good';
    case Good = 'Good';
    case NeedsImprovement = 'Needs Improvement';
    case Unsatisfactory = 'Unsatisfactory';

    public function color(): string
    {
        return match ($this) {
            self::Excellent => 'emerald',
            self::VeryGood => 'blue',
            self::Good => 'amber',
            self::NeedsImprovement => 'orange',
            self::Unsatisfactory => 'rose',
        };
    }

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 90.00 => self::Excellent,
            $score >= 80.00 => self::VeryGood,
            $score >= 70.00 => self::Good,
            $score >= 60.00 => self::NeedsImprovement,
            default => self::Unsatisfactory,
        };
    }
}
