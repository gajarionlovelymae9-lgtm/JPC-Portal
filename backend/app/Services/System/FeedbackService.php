<?php

namespace App\Services\System;

use App\Models\AiFeedback;
use App\Models\User;
use App\Models\UserFeedback;
use App\Services\Audit\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * User feedback (bug/feature/report) + AI quality feedback loop.
 *
 * AI feedback records how authorized users rate AI output without mutating the
 * original historical AI result and never touches official performance scores.
 */
class FeedbackService
{
    public function __construct(
        private readonly ActivityLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, array $data): UserFeedback
    {
        $feedback = UserFeedback::create([
            'user_id' => $user->id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'low',
            'status' => UserFeedback::STATUS_OPEN,
        ]);

        $this->audit->log(
            'feedback.submit',
            "Feedback submitted: {$feedback->subject}",
            $user,
            $feedback,
            ['category' => $feedback->category, 'priority' => $feedback->priority],
        );

        return $feedback;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordAiFeedback(User $user, array $data): AiFeedback
    {
        $feedback = AiFeedback::create([
            'user_id' => $user->id,
            'ai_type' => $data['ai_type'],
            'ai_record_id' => $data['ai_record_id'] ?? null,
            'category' => $data['category'] ?? 'quality_feedback',
            'verdict' => $data['verdict'],
            'comment' => $data['comment'] ?? null,
        ]);

        $this->audit->log(
            'ai.feedback',
            "AI feedback recorded: {$feedback->ai_type} -> {$feedback->verdict}",
            $user,
            $feedback,
            ['ai_type' => $feedback->ai_type],
        );

        return $feedback;
    }

    public function list(Request $request): LengthAwarePaginator
    {
        $query = UserFeedback::query()->with('user:id,name,email')->with('assignee:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        return $query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 15), 50));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $admin, UserFeedback $feedback, array $data): UserFeedback
    {
        if (isset($data['status'])) {
            $feedback->status = $data['status'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $feedback->assigned_to = $data['assigned_to'];
        }
        if (array_key_exists('priority', $data)) {
            $feedback->priority = $data['priority'];
        }
        if (array_key_exists('resolution', $data)) {
            $feedback->resolution = $data['resolution'];
            if (in_array($data['status'] ?? $feedback->status, [UserFeedback::STATUS_RESOLVED, UserFeedback::STATUS_CLOSED], true)) {
                $feedback->resolved_at = now();
            }
        }
        if (isset($data['status']) && in_array($data['status'], [UserFeedback::STATUS_RESOLVED, UserFeedback::STATUS_CLOSED, UserFeedback::STATUS_REJECTED], true) && $feedback->resolved_at === null) {
            $feedback->resolved_at = now();
        }
        $feedback->save();

        $this->audit->log(
            'feedback.update',
            "Feedback #{$feedback->id} updated to {$feedback->status}",
            $admin,
            $feedback,
            ['status' => $feedback->status],
        );

        return $feedback->load(['user:id,name,email', 'assignee:id,name']);
    }

    /**
     * Validate status transitions against the documented lifecycle.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validateStatus(array $data): void
    {
        if (isset($data['status']) && ! in_array($data['status'], UserFeedback::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status. Allowed: '.implode(', ', UserFeedback::STATUSES),
            ]);
        }
    }
}
