<?php

namespace App\Models;

use Database\Factories\EmployeeTargetNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target achievement note — a note an employee records against their own target
 * accomplishments. Notes are authored and managed by the owning employee only.
 */
class EmployeeTargetNote extends Model
{
    /** @use HasFactory<EmployeeTargetNoteFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_target_id',
        'employee_id',
        'body',
    ];

    /**
     * The target the note relates to.
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class, 'employee_target_id');
    }

    /**
     * The employee who authored the note.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
