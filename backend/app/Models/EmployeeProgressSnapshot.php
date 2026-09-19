<?php

namespace App\Models;

use Database\Factories\EmployeeProgressSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProgressSnapshot extends Model
{
    /** @use HasFactory<EmployeeProgressSnapshotFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'academic_year_id',
        'snapshot_date',
        'total_tasks',
        'completed_tasks',
        'pending_tasks',
        'overdue_tasks',
        'total_reports',
        'submitted_reports',
        'approved_reports',
        'late_reports',
'completion_rate',
            'on_time_rate',
            'approval_rate',
            'target_achievement_rate',
            'overall_score',
            'finalized',
            'finalized_by',
        ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_tasks' => 'integer',
            'completed_tasks' => 'integer',
            'pending_tasks' => 'integer',
            'overdue_tasks' => 'integer',
            'total_reports' => 'integer',
            'submitted_reports' => 'integer',
            'approved_reports' => 'integer',
            'late_reports' => 'integer',
            'completion_rate' => 'decimal:2',
            'on_time_rate' => 'decimal:2',
            'approval_rate' => 'decimal:2',
            'target_achievement_rate' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'finalized' => 'boolean',
            'finalized_at' => 'datetime',
            'finalized_by' => 'integer',
        ];
    }

    /**
     * The employee this snapshot captures.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The academic year this snapshot belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
