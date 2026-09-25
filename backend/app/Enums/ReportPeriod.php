<?php

namespace App\Enums;

/**
 * ReportPeriod — reporting periods (Phase 9 spec item 4).
 *
 * The single source of truth for period types used by ReportingService,
 * AnalyticsService and all export/date-filter components. Server-side date
 * handling is mandatory; these keys are validated by ReportFilterRequest so an
 * attacker cannot inject an arbitrary period label.
 */
enum ReportPeriod: string
{
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Quarterly = 'QUARTERLY';
    case Yearly = 'YEARLY';
    case Custom = 'CUSTOM';

    /**
     * Iterator helper for dropdowns / filter UI ordering.
     */
    public static function options(): array
    {
        return [
            self::Daily,
            self::Weekly,
            self::Monthly,
            self::Quarterly,
            self::Yearly,
            self::Custom,
        ];
    }

    /**
     * Whether this period requires a user-supplied start/end boundary.
     */
    public function requiresCustomRange(): bool
    {
        return $this === self::Custom;
    }

    /**
     * Human-readable label used by API resources and management reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
            self::Custom => 'Custom Range',
        };
    }
}
