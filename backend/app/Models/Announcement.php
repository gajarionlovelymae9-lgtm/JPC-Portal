<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    /** Allowed values for the category column (validated on the API). */
    public const CATEGORIES = [
        'general', 'academic', 'event', 'deadline', 'policy', 'reminder', 'other', 'holiday',
    ];

    protected $fillable = [
        'title',
        'message',
        'priority',
        'category',
        'audience_type',
        'audience_id',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for announcements visible to a given user.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $roleName = $user->role ?? $user->roles->first()?->name;
        $deptId = $user->employee?->department_id;

        return $query->where('status', 'published')
            ->where(function (Builder $q) use ($user, $roleName, $deptId) {
                $q->where('audience_type', 'all')
                    ->orWhere(function (Builder $sq) use ($roleName) {
                        $sq->where('audience_type', 'role')
                            ->where('audience_id', $roleName);
                    })
                    ->orWhere(function (Builder $sq) use ($deptId) {
                        if ($deptId) {
                            $sq->where('audience_type', 'department')
                                ->where('audience_id', (string) $deptId);
                        }
                    })
                    ->orWhere(function (Builder $sq) use ($user) {
                        $sq->where('audience_type', 'user')
                            ->where('audience_id', (string) $user->id);
                    });
            })
            ->where(function (Builder $q) {
                // Visibility is gated on the publication time, not on the
                // event's start date: start_date/end_date describe the event
                // window for the calendar, while published_at controls when
                // the announcement becomes visible. A scheduled announcement
                // must never appear before its publication time.
                $now = now();
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $q) {
                $now = now();
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            });
    }
}
