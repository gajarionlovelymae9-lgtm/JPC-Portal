<?php

namespace App\Models;

use Database\Factories\EmployeeTargetAdjustmentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target adjustment request — an employee asks to change an assigned target and
 * an administrator approves or rejects it.
 *
 * `original_snapshot` preserves the target's values *before* an approved change
 * was applied, so applying an adjustment never loses the original target
 * information. The full request/decision history is also appended to the
 * existing append-only `activity_logs` trail by the service.
 */
class EmployeeTargetAdjustmentRequest extends Model
{
    /** @use HasFactory<EmployeeTargetAdjustmentRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_target_id',
        'employee_id',
        'requested_by',
        'reason',
        'request_type',
        'proposed_target_value',
        'proposed_target_unit',
        'proposed_start_date',
        'proposed_end_date',
        'proposed_description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
        'original_snapshot',
        'applied_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_target_value' => 'decimal:2',
            'proposed_start_date' => 'date',
            'proposed_end_date' => 'date',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'original_snapshot' => 'array',
        ];
    }

    /**
     * The target the adjustment applies to.
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class, 'employee_target_id');
    }

    /**
     * The employee the target belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The user who submitted the request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The administrator who reviewed the request.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Only requests still awaiting a decision.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
