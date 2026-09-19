<?php

namespace App\Enums;

/**
 * ReportFormat — Phase 9 export formats (spec items 44-45, 63).
 *
 * Backend must honor these exact values; frontend export menu maps UI labels
 * to them. Never treat arbitrary client strings as formats.
 */
enum ReportFormat: string
{
    case Csv = 'CSV';
    case Excel = 'EXCEL';
    case Pdf = 'PDF';

    /**
     * File extension used when persisting the generated export.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Csv => 'csv',
            self::Excel => 'xlsx',
            self::Pdf => 'pdf',
        };
    }

    /**
     * Canonical MIME type. Frontend download attribution and the secure
     * export store both rely on this.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    /**
     * Human-readable label for UI and audit metadata.
     */
    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Excel => 'Excel',
            self::Pdf => 'PDF',
        };
    }
}
