<?php

namespace App\Models;

use Database\Factories\TaskReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskReference extends Model
{
    /** @use HasFactory<TaskReferenceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'reference_type',
        'reference_url',
        'file_path',
    ];

    /**
     * The task this reference is attached to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Report references matching this task reference during AI validation.
     */
    public function reportReferences(): HasMany
    {
        return $this->hasMany(ReportReference::class);
    }
}
