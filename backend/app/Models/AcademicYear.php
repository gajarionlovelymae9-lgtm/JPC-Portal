<?php

namespace App\Models;

use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * The attribute that should be used as the primary key.
     *
     * @var string
     */
    protected $table = 'academic_years';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Annual plans scoped to this academic year.
     */
    public function annualPlans(): HasMany
    {
        return $this->hasMany(AnnualPlan::class);
    }

    /**
     * Employee targets scoped to this academic year (through annual plans).
     */
    public function employeeTargets(): HasManyThrough
    {
        return $this->hasManyThrough(EmployeeTarget::class, AnnualPlan::class);
    }

    /**
     * Progress snapshots captured for this academic year.
     */
    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(EmployeeProgressSnapshot::class);
    }

    /**
     * Performance records for this academic year.
     */
    public function performanceRecords(): HasMany
    {
        return $this->hasMany(EmployeePerformanceRecord::class);
    }

    /**
     * Badge awards granted within this academic year.
     */
    public function employeeBadges(): HasMany
    {
        return $this->hasMany(EmployeeBadge::class);
    }

    /**
     * Monthly achievements computed for this academic year.
     */
    public function monthlyAchievements(): HasMany
    {
        return $this->hasMany(MonthlyAchievement::class);
    }

    /**
     * Only the currently-active academic year(s).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Only closed academic years.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }
}
