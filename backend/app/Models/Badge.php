<?php

namespace App\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'category',
        'tier',
        'criteria_type',
        'criteria_value',
        'is_active',
        'allow_repeats',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criteria_value' => 'array',
            'is_active' => 'boolean',
            'allow_repeats' => 'boolean',
        ];
    }

    /**
     * Employee award join records for this badge.
     */
    public function employeeBadges(): HasMany
    {
        return $this->hasMany(EmployeeBadge::class);
    }

    /**
     * Employees awarded this badge through the join table.
     *
     * Inverse of Employee::badges(); the pivot carries the award metadata.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_badges', 'badge_id', 'employee_id')
            ->withPivot(['academic_year_id', 'period_start', 'period_end', 'awarded_at', 'awarded_by', 'criteria_snapshot'])
            ->withTimestamps();
    }

    /**
     * Only currently-active badges.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
