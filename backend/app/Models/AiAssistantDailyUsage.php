<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantDailyUsage extends Model
{
    protected $fillable = [
        'user_id',
        'usage_date',
        'request_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'request_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
