<?php

namespace App\Enums;

/**
 * Issue categories a Department Head may escalate to Administration.
 *
 * Escalations cover issues that need formal intervention; they deliberately do
 * not replace ordinary deadline reminders or the Late Submissions feature.
 */
enum EscalationType: string
{
    case RepeatedLateSubmission = 'repeated_late_submission';
    case UnresolvedOverdueTask = 'unresolved_overdue_task';
    case MissingRequiredReport = 'missing_required_report';
    case TargetAtRisk = 'target_at_risk';
    case OtherSeriousIssue = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RepeatedLateSubmission => 'Repeated Late Submission',
            self::UnresolvedOverdueTask => 'Unresolved Overdue Task',
            self::MissingRequiredReport => 'Missing Required Report',
            self::TargetAtRisk => 'Target at Risk',
            self::OtherSeriousIssue => 'Other Serious Issue',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
