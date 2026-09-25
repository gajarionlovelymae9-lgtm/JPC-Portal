<?php

namespace App\Models;

use App\Enums\PerformanceRating;
use Database\Factories\EmployeePerformanceRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePerformanceRecord extends Model
{
    /** @use HasFactory<EmployeePerformanceRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * Computed verdict fields (overall_score, productivity_score, rating,
     * performance_status, calculation_version, breakdown_data, calculated_at)
     * are intentionally NOT fillable — they are written only by the
     * PerformanceSnapshotService after a validated recalculation.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'academic_year_id',
        'performance_period_id',
        'period_type',
        'period_start',
        'period_end',
        'completion_rate',
        'report_completion_rate',
        'on_time_rate',
        'approval_rate',
        'quality_score',
        'target_achievement_rate',
        'metadata',
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
            'completion_rate' => 'decimal:2',
            'report_completion_rate' => 'decimal:2',
            'on_time_rate' => 'decimal:2',
            'approval_rate' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'target_achievement_rate' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'rating' => PerformanceRating::class,
            'breakdown_data' => 'array',
            'metadata' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * The employee this record describes.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The academic year this record belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * The performance period this record belongs to.
     */
    public function performancePeriod(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class);
    }

    /**
     * Detailed breakdown items for this performance record.
     */
    public function breakdowns(): HasMany
    {
        return $this->hasMany(PerformanceScoreBreakdown::class, 'performance_record_id');
    }

    /**
     * Monthly performance records.
     */
    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('period_type', 'monthly');
    }

    /**
     * Quarterly performance records.
     */
    public function scopeQuarterly(Builder $query): Builder
    {
        return $query->where('period_type', 'quarterly');
    }

    /**
     * Yearly performance records.
     */
    public function scopeYearly(Builder $query): Builder
    {
        return $query->where('period_type', 'yearly');
    }

    /**
     * Records flagged as at-risk performers.
     */
    public function scopeAtRisk(Builder $query): Builder
    {
        return $query->where('performance_status', 'at_risk');
    }
}
