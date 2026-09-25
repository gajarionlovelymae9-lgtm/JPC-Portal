<?php

namespace App\Enums;

enum DataQualityIssueSeverity: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 3,
            self::HIGH => 7,
            self::CRITICAL => 15,
        };
    }
}
