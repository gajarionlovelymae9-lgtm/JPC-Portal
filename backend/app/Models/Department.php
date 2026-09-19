<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
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
        'status',
    ];

    /**
     * Employees assigned to this department.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Historical department-head assignment records.
     */
    public function departmentHeads(): HasMany
    {
        return $this->hasMany(DepartmentHead::class);
    }

    /**
     * Annual plans scoped to this department.
     */
    public function annualPlans(): HasMany
    {
        return $this->hasMany(AnnualPlan::class);
    }

    /**
     * Department plans scoped to this department.
     */
    public function departmentPlans(): HasMany
    {
        return $this->hasMany(DepartmentPlan::class);
    }

    /**
     * Department targets scoped to this department.
     */
    public function departmentTargets(): HasMany
    {
        return $this->hasMany(DepartmentTarget::class);
    }

    /**
     * Tasks scoped to this department.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Only currently-active departments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
