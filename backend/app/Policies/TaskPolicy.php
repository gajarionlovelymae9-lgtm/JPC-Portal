<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasPermission('tasks.update')) {
            return true;
        }

        return $user->id === $task->created_by && $task->status !== 'completed';
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.delete');
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.assign')
            && in_array($task->status, ['draft', 'assigned'], true);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.assign') || $user->id === $task->created_by;
    }

    public function viewAny(User $user, $model = null): bool
    {
        return $user->hasPermission('tasks.view');
    }

    /**
     * Administrative dashboards (task summary, progress, achievement) are
     * reserved for managers, not regular employees. HR gains access through
     * its configured permissions (analytics.view, tasks.view).
     */
    public function viewAnalytics(User $user): bool
    {
        return in_array($user->roleName(), ['admin', 'hr', 'department_head'], true)
            && $user->hasPermission('tasks.view');
    }
}
