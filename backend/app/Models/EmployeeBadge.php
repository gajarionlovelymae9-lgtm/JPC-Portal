<?php

namespace App\Models;

use Database\Factories\EmployeeBadgeFactory;
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
     * Award grantor and timestamp are set by internal service code only.
     *
     * @var list<string>
     */
    protected $guarded = ['awarded_at', 'awarded_by'];

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
}
