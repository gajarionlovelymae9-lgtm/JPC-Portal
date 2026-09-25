<?php

namespace App\Models;

use App\Enums\PeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformancePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'period_type',
        'year',
        'month',
        'quarter',
        'start_date',
        'end_date',
        'status',
        'calculated_at',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'quarter' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function performanceRecords(): HasMany
    {
        return $this->hasMany(EmployeePerformanceRecord::class);
    }

    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('period_type', 'monthly');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public static function findOrCreateMonthly(int $year, int $month): self
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $name = $start->format('F Y');

        return static::firstOrCreate(
            [
                'period_type' => 'monthly',
                'year' => $year,
                'month' => $month,
            ],
            [
                'name' => $name,
                'quarter' => (int) ceil($month / 3),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'open',
            ]
        );
    }
}
