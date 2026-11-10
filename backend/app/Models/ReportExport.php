<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ReportExport — Phase 9 asynchronous export pipeline record (spec items
 * 32-33, 53-55).
 *
 * Large exports are generated in the background (QUUEUED → PROCESSING →
 * COMPLETED / FAILED → EXPIRED). Files live on the private `report_exports`
 * disk under unpredictable names; download only happens through this model so
 * ownership and status are always enforced.
 */
class ReportExport extends Model
{
    use HasFactory;

    public const DISK = 'report_exports';

    protected $fillable = [
        'user_id',
        'report_type',
        'format',
        'status',
        'filters',
        'file_path',
        'file_name',
        'file_size',
        'row_count',
        'error_line',
        'error_message',
        'requested_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'json',
        'file_size' => 'integer',
        'row_count' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): ReportExportStatus
    {
        return ReportExportStatus::from($this->status);
    }

    public function isTerminal(): bool
    {
        return $this->status()->isTerminal();
    }

    public function isReadyForDownload(): bool
    {
        return $this->status()->isReadyForDownload() && $this->file_path !== null;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Canonical filter equality for the duplicate-submit guard: key order
     * must not matter, so both sides go through a recursive sort before the
     * string comparison.
     */
    public function isSameFilters(array $filters): bool
    {
        $canonical = static function (array $value) use (&$canonical): array {
            ksort($value);

            return array_map(
                fn ($item) => is_array($item) ? $canonical($item) : $item,
                $value,
            );
        };

        return json_encode($canonical($this->filters ?? [])) === json_encode($canonical($filters));
    }

    /**
     * Absolute path on the download server for streaming the file.
     */
    public function absolutePath(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        return Storage::disk(self::DISK)->path($this->file_path);
    }

    /**
     * Physical presence check — guards against a stale DB row whose file has
     * already been cleaned up.
     */
    public function fileExists(): bool
    {
        return $this->file_path !== null && Storage::disk(self::DISK)->exists($this->file_path);
    }
}
