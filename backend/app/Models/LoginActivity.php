<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'meta',
        'logged_out_at',
    ];

    /**
     * The table has no updated_at column; created_at is managed by the DB.
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'logged_out_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user the activity belongs to (if still present).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
