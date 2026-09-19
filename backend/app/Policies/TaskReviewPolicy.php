<?php

namespace App\Policies;

use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskReviewPolicy
{
    use HandlesAuthorization;

    public function review(User $user): bool
    {
        return in_array($user->roleName(), ['admin', 'department_head'], true)
            && ($user->hasPermission('tasks.update') || $user->hasPermission('tasks.complete'));
    }

    public function create(User $user): bool
    {
        return $this->review($user);
    }

    public function approve(User $user, TaskAssignment $assignment): bool
    {
        if (! $this->review($user)) {
            return false;
        }

        return $user->id !== $assignment->employee?->user_id;
    }

    public function reject(User $user, TaskAssignment $assignment): bool
    {
        return $this->approve($user, $assignment);
    }

    public function requestRevision(User $user, TaskAssignment $assignment): bool
    {
        return $this->approve($user, $assignment);
    }
}
