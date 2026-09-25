<?php

namespace App\Enums;

enum DataQualityIssueStatus: string
{
    case OPEN = 'OPEN';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case CONFIRMED = 'CONFIRMED';
    case DISMISSED = 'DISMISSED';
    case RESOLVED = 'RESOLVED';
    case IGNORED = 'IGNORED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::DISMISSED, self::RESOLVED, self::IGNORED], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OPEN, self::UNDER_REVIEW, self::CONFIRMED], true);
    }
}
