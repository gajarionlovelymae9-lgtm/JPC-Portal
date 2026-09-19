<?php

namespace App\Enums;

/**
 * Manual review decisions an authorized reviewer can apply to a report.
 *
 * "Approved" and "Rejected" are final for the current version; requesting a
 * revision sends the report back to the employee who may then upload a new
 * version (never destroying the previous one).
 */
enum ReportReviewDecision: string
{
    case Approved = 'approved';
    case RevisionRequired = 'revision_required';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::RevisionRequired => 'Request Revision',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Whether this decision requires free-text remarks from the reviewer.
     */
    public function requiresRemarks(): bool
    {
        return $this === self::RevisionRequired || $this === self::Rejected;
    }
}