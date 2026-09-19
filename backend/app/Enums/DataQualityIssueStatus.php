<?php

namespace App\Enums;

/**
 * DataQualityIssueStatus — Phase 8 human-review lifecycle (spec item 6).
 *
 * Lifecycle:
 *   OPEN
 *     -> UNDER_REVIEW -> CONFIRMED -> RESOLVED
 *   OPEN
 *     -> UNDER_REVIEW -> DISMISSED
 *   IGNORED is available for quarantined/auto-suppressed issues.
 *
 * Transition validation is centralized so no controller or service hardcodes
 * transition rules in multiple places (spec item 70 idempotency + item 42
 * safety).
 */
enum DataQualityIssueStatus: string
{
    case Open = 'OPEN';
    case UnderReview = 'UNDER_REVIEW';
    case Confirmed = 'CONFIRMED';
    case Dismissed = 'DISMISSED';
    case Resolved = 'RESOLVED';
    case Ignored = 'IGNORED';

    /**
     * Whether this status requires a human reviewer to have been assigned.
     */
    public function isHumanTouched(): bool
    {
        return in_array($this, [self::UnderReview, self::Confirmed, self::Dismissed, self::Resolved], true);
    }

    /**
     * Whether this status represents a closed (no longer actionable) state.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Dismissed, self::Resolved, self::Ignored], true);
    }

    /**
     * Allowed next statuses for a given current status. Returning an empty
     * array means the status is terminal.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::UnderReview, self::Dismissed],
            self::UnderReview => [self::Confirmed, self::Dismissed, self::Ignored],
            self::Confirmed => [self::Resolved, self::Dismissed],
            self::Resolved => [],
            self::Dismissed => [],
            self::Ignored => [self::Open],
        };
    }

    /**
     * Whether a transition from $from to $to is allowed. Always allows a
     * transition to a closed state for safety unless already closed.
     */
    public static function canTransition(self $from, self $to): bool
    {
        if ($from === $to) {
            return true;
        }
        return in_array($to, $from->allowedTransitions(), true);
    }
}
