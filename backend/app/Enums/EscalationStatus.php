<?php

namespace App\Enums;

/**
 * Lifecycle of a department escalation.
 *
 * A Department Head raises an escalation in `pending`. Administration reviews
 * it (`under_review`), may request further input (`action_required`), and
 * finally closes it as `resolved` or `rejected`. Both terminal states cannot
 * be reopened through the normal workflow.
 */
enum EscalationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case ActionRequired = 'action_required';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::UnderReview => 'Under Review',
            self::ActionRequired => 'Action Required',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Terminal statuses can no longer move through the workflow.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected], true);
    }

    /**
     * Statuses that still require attention (used for "open" counters).
     *
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return [self::Pending, self::UnderReview, self::ActionRequired];
    }

    /**
     * Allowed next statuses from the current one.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::ActionRequired, self::Resolved, self::Rejected],
            self::UnderReview => [self::ActionRequired, self::Resolved, self::Rejected],
            self::ActionRequired => [self::UnderReview, self::Resolved, self::Rejected],
            self::Resolved, self::Rejected => [],
        };
    }

    /**
     * Whether the workflow permits moving to the given status.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
