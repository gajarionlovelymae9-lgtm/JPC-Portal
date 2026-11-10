<?php

namespace App\Models;

use App\Enums\AiInsightType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'department_id', 'type', 'content', 'metadata', 'generated_at'];

    protected $casts = ['type' => AiInsightType::class, 'metadata' => 'array', 'generated_at' => 'datetime'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
