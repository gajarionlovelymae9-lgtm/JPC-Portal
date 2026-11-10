<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'position_id',
        'department_id',
        'employment_status',
        'employment_type',
        'supervisor_id',
        'date_of_birth',
        'date_hired',
        'date_ended',
        'phone',
        'address',
        'profile_photo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_hired' => 'date',
            'date_ended' => 'date',
        ];
    }

    /**
     * The linked login account (1:1).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The position this employee holds.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * The line manager / supervisor of this employee (self-referencing).
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Employees directly supervised by this employee (self-referencing).
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    /**
     * Append-only history of profile field changes for this employee.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    /**
     * Historical department-head assignment records.
     */
    public function departmentHeadAssignments(): HasMany
    {
        return $this->hasMany(DepartmentHead::class);
    }

    /**
     * Targets set for this employee within annual plans.
     */
    public function employeeTargets(): HasMany
    {
        return $this->hasMany(EmployeeTarget::class);
    }

    /**
     * Task assignment records for this employee.
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Target adjustment requests raised by this employee.
     */
    public function targetAdjustmentRequests(): HasMany
    {
        return $this->hasMany(EmployeeTargetAdjustmentRequest::class);
    }

    /**
     * Achievement notes this employee recorded against their targets.
     */
    public function targetNotes(): HasMany
    {
        return $this->hasMany(EmployeeTargetNote::class);
    }

    /**
     * Report submissions by this employee.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Progress snapshot history for this employee.
     */
    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(EmployeeProgressSnapshot::class);
    }

    /**
     * Performance records for this employee.
     */
    public function performanceRecords(): HasMany
    {
        return $this->hasMany(EmployeePerformanceRecord::class);
    }

    /**
     * Badge award join records for this employee.
     */
    public function employeeBadges(): HasMany
    {
        return $this->hasMany(EmployeeBadge::class);
    }

    /**
     * Badges earned by this employee through the award join table.
     *
     * The employee_badges junction holds the award metadata (period, awarded_by,
     * criteria snapshot), so the earned badges are exposed as a many-to-many
     * relation with the pivot attributes exposed on each badge.
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'employee_badges', 'employee_id', 'badge_id')
            ->withPivot(['academic_year_id', 'period_start', 'period_end', 'awarded_at', 'awarded_by', 'criteria_snapshot'])
            ->withTimestamps();
    }

    /**
     * Monthly achievement summary records for this employee.
     */
    public function monthlyAchievements(): HasMany
    {
        return $this->hasMany(MonthlyAchievement::class);
    }

    /**
     * Concatenated display name without redundant whitespace.
     *
     * "first [middle] last [suffix]" — empty parts are skipped. Not stored in
     * the database; available as `$employee->full_name`.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect([
                $this->first_name,
                $this->middle_name,
                $this->last_name,
                $this->suffix,
            ])->filter(fn (?string $part): bool => filled($part))->implode(' '),
        );
    }

    /**
     * Only currently-active employees.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }

    /**
     * Employees that are not currently active (any non-active status).
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('employment_status', '!=', 'active');
    }

    /**
     * Restrict to employees in the given department.
     */
    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Restrict to employees holding the given position.
     */
    public function scopeByPosition(Builder $query, int $positionId): Builder
    {
        return $query->where('position_id', $positionId);
    }

    /**
     * Restrict to employees with the given employment type (e.g. 'regular').
     */
    public function scopeOfEmploymentType(Builder $query, string $employmentType): Builder
    {
        return $query->where('employment_type', $employmentType);
    }
}
