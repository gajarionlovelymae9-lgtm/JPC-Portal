<?php

namespace App\Models;

use Database\Factories\ReportReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportReference extends Model
{
    /** @use HasFactory<ReportReferenceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'task_reference_id',
        'reference_text',
        'reference_url',
    ];

    /**
     * The report this reference belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The matching task reference when linked during AI validation.
     */
    public function taskReference(): BelongsTo
    {
        return $this->belongsTo(TaskReference::class);
    }
}
