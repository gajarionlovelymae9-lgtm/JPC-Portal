<?php

namespace App\Models;

use Database\Factories\EmployeeBadgeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBadge extends Model
{
    /** @use HasFactory<EmployeeBadgeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'badge_id',
        'academic_year_id',
        'period_start',
        'period_end',
        'criteria_snapshot',
    ];

    /**
     * Award grantor, verification verdicts and timestamps are set by internal
     * service code only; clients can never submit them.
     *
     * @var list<string>
     */
    protected $guarded = [
        'awarded_at',
        'awarded_by',
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'evidence',
        // Department Head recommendation metadata is written only by the
        // recommendation service so clients can never fake a recommender.
        'recommended_by',
        'recommended_at',
        'recommendation_details',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'awarded_at' => 'datetime',
            'verified_at' => 'datetime',
            'recommended_at' => 'datetime',
            'criteria_snapshot' => 'array',
        ];
    }

    /**
     * The employee who received the badge.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The badge awarded.
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * The academic year the award belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * The user who awarded the badge.
     */
    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /**
     * The admin who verified (or rejected) the award.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Awards still awaiting admin review.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Awards that passed admin review (or were auto-verified on grant).
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Awards that were turned down during review.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('verification_status', 'rejected');
    }

    /**
     * Awards created from a Department Head recommendation.
     */
    public function scopeRecommended(Builder $query): Builder
    {
        return $query->whereNotNull('recommended_by');
    }

    /**
     * The Department Head user who recommended this recognition.
     */
    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }
}
