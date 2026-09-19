<?php

namespace App\Policies;

use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskAssignmentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            || $this->isManager($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.assign');
    }

    public function accept(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    public function start(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    public function submit(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    public function review(User $user, TaskAssignment $assignment): bool
    {
        return in_array($user->roleName(), ['admin', 'department_head'], true)
            && $this->canPerformAssignedWork($user);
    }

    public function resubmit(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    public function progress(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    public function delete(User $user, TaskAssignment $assignment): bool
    {
        return $user->hasPermission('tasks.delete');
    }

    public function viewAny(User $user, $model = null): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function updateProgress(User $user, TaskAssignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $this->canPerformAssignedWork($user);
    }

    /**
     * Whether the given user owns the employee the assignment targets.
     *
     * TaskAssignment.employee_id references employees.id, so ownership must
     * be resolved through the related Employee's user_id — not compared
     * directly against the User id.
     */
    private function ownsAssignment(User $user, TaskAssignment $assignment): bool
    {
        $ownerUserId = $assignment->employee?->user_id;

        return $ownerUserId !== null && (int) $ownerUserId === (int) $user->id;
    }

    /**
     * Employees hold tasks.complete (not tasks.update) while managers hold
     * both, so assigned-work actions accept either permission.
     */
    private function canPerformAssignedWork(User $user): bool
    {
        return $user->hasPermission('tasks.update')
            || $user->hasPermission('tasks.complete');
    }

    private function isManager(User $user): bool
    {
        return in_array($user->roleName(), ['admin', 'department_head'], true)
            && $user->hasPermission('tasks.view');
    }
}
