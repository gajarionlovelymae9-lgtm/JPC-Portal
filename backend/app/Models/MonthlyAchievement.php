<?php

namespace App\Models;

use Database\Factories\MonthlyAchievementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAchievement extends Model
{
    /** @use HasFactory<MonthlyAchievementFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'academic_year_id',
        'month',
        'year',
    ];

    /**
     * Achievement verdict fields computed from the performance dataset and
     * written only by internal achievement services/seeders.
     *
     * @var list<string>
     */
    protected $guarded = [
        'completion_rate',
        'on_time_rate',
        'approved_reports',
        'completed_tasks',
        'overall_score',
        'rank',
        'is_employee_of_month',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'completion_rate' => 'decimal:2',
            'on_time_rate' => 'decimal:2',
            'approved_reports' => 'integer',
            'completed_tasks' => 'integer',
            'overall_score' => 'decimal:2',
            'rank' => 'integer',
            'is_employee_of_month' => 'boolean',
        ];
    }

    /**
     * The employee this achievement belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The academic year this achievement belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Restrict to a given calendar month of a given year.
     */
    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('month', $month)->where('year', $year);
    }

    /**
     * Only employees of the month.
     */
    public function scopeEmployeeOfMonth(Builder $query): Builder
    {
        return $query->where('is_employee_of_month', true);
    }

    /**
     * Best-ranked employees first (rank 1 = top).
     */
    public function scopeTopRanked(Builder $query): Builder
    {
        return $query->whereNotNull('rank')->orderBy('rank');
    }
}
