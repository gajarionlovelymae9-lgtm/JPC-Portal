<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceScoreBreakdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_record_id',
        'metric_key',
        'metric_label',
        'raw_value',
        'weight',
        'weighted_value',
        'calculation_version',
    ];

    protected function casts(): array
    {
        return [
            'raw_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'weighted_value' => 'decimal:2',
        ];
    }

    public function performanceRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeePerformanceRecord::class, 'performance_record_id');
    }
}
