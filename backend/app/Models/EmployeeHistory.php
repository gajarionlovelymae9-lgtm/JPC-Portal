<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHistory extends Model
{
    /**
     * Append-only change history for an employee profile field. Intentionally
     * has no SoftDeletes: historical records must survive.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'field_name',
        'old_value',
        'new_value',
        'effective_date',
        'reason',
        'changed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    /**
     * The employee whose profile field changed.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The user account that recorded the change (nullable for system events).
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
