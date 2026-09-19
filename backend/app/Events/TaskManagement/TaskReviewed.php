<?php

namespace App\Events\TaskManagement;

use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TaskAssignment $assignment,
        public User $reviewer,
        public TaskReview $review,
    ) {}
}