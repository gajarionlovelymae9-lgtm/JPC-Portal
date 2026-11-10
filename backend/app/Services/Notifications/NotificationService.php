<?php

namespace App\Services\Notifications;

use App\Enums\Role as RoleEnum;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;
use App\Notifications\Tasks\TaskApprovedNotification;
use App\Notifications\Tasks\TaskAssignedNotification;
use App\Notifications\Tasks\TaskCancelledNotification;
use App\Notifications\Tasks\TaskDeadlineReminderNotification;
use App\Notifications\Tasks\TaskOverdueNotification;
use App\Notifications\Tasks\TaskRejectedNotification;
use App\Notifications\Tasks\TaskResubmittedNotification;
use App\Notifications\Tasks\TaskRevisionRequiredNotification;
use App\Notifications\Tasks\TaskSubmittedNotification;
use Illuminate\Support\Collection;

/**
 * Central sender for every in-app workflow notification.
 *
 * Recipients mirror the authorization model of the task module:
 *   - employees receive assignments, deadline reminders, overdue alerts and
 *     review results;
 *   - admins and the owning department head receive submission/resubmission
 *     review-queue alerts and assignment/overdue management alerts.
 *
 * Deadline reminders and overdue alerts are idempotent: the senders check for
 * an existing notification row (user, type, task, reminder_days) before
 * creating a new one, so scheduler re-runs never duplicate.
 */
class NotificationService
{
    public function sendTaskAssigned(TaskAssignment $assignment): void
    {
        $employeeUser = $assignment->employee?->user;

        if ($employeeUser) {
            $employeeUser->notify(new TaskAssignedNotification($assignment, $employeeUser));
        }

        $this->departmentHeads($assignment->task)
            ->each(fn (User $user) => $user->notify(new TaskAssignedNotification($assignment, $user)));
    }

    public function sendDeadlineReminder(TaskAssignment $assignment, int $daysBefore): void
    {
        $employeeUser = $assignment->employee?->user;

        if (! $employeeUser || $this->alreadySent($employeeUser, $assignment->task_id, 'deadline_reminder', $daysBefore)) {
            return;
        }

        $employeeUser->notify(new TaskDeadlineReminderNotification($assignment, $employeeUser, $daysBefore));
    }

    public function sendOverdueNotification(TaskAssignment $assignment, int $daysOverdue): void
    {
        $recipients = collect();

        if ($employeeUser = $assignment->employee?->user) {
            $recipients->push($employeeUser);
        }

        $recipients = $recipients->concat($this->overdueManagementRecipients($assignment->task));

        $recipients->unique('id')
            ->filter()
            ->each(function (User $user) use ($assignment, $daysOverdue): void {
                if ($this->alreadySent($user, $assignment->task_id, 'task_overdue', Notification::REMINDER_DAYS_OVERDUE)) {
                    return;
                }

                $user->notify(new TaskOverdueNotification($assignment, $user, $daysOverdue));
            });
    }

    public function sendTaskSubmitted(TaskAssignment $assignment): void
    {
        $this->reviewers($assignment->task)
            ->each(fn (User $user) => $user->notify(new TaskSubmittedNotification($assignment, $user)));
    }

    public function sendTaskResubmitted(TaskAssignment $assignment): void
    {
        $this->reviewers($assignment->task)
            ->each(fn (User $user) => $user->notify(new TaskResubmittedNotification($assignment, $user)));
    }

    public function sendReviewed(TaskAssignment $assignment, TaskReview $review): void
    {
        $employeeUser = $assignment->employee?->user;

        if (! $employeeUser) {
            return;
        }

        $notification = match ($review->decision) {
            'approved' => new TaskApprovedNotification($assignment, $employeeUser, $review),
            'rejected' => new TaskRejectedNotification($assignment, $employeeUser, $review),
            default => new TaskRevisionRequiredNotification($assignment, $employeeUser, $review),
        };

        $employeeUser->notify($notification);
    }

    public function sendTaskCancelled(Task $task): void
    {
        $task->assignments
            ->map(fn (TaskAssignment $assignment) => $assignment->employee?->user)
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new TaskCancelledNotification($task, $user)));
    }

    /**
     * Authorized reviewers: admins plus the department head(s) of the task's
     * department. Mirrors TaskReviewService::AUTHORIZED_ROLES.
     *
     * @return Collection<int, User>
     */
    private function reviewers(Task $task): Collection
    {
        $users = collect();

        if ($adminRoleId = $this->roleId(RoleEnum::Admin)) {
            $users = $users->concat(User::query()->where('role_id', $adminRoleId)->get());
        }

        return $users->concat($this->departmentHeads($task))->unique('id')->values();
    }

    /**
     * Management recipients for overdue alerts, driven by
     * config('task.notifications.overdue_management_roles').
     *
     * @return Collection<int, User>
     */
    private function overdueManagementRecipients(Task $task): Collection
    {
        $roles = config('task.notifications.overdue_management_roles', ['department_head']);
        $recipients = collect();

        if (in_array('department_head', $roles, true)) {
            $recipients = $recipients->concat($this->departmentHeads($task));
        }

        $roleNames = array_values(array_intersect($roles, ['hr', 'admin']));

        if ($roleNames !== []) {
            $roleIds = Role::query()->whereIn('name', $roleNames)->pluck('id');

            if ($roleIds->isNotEmpty()) {
                $recipients = $recipients->concat(User::query()->whereIn('role_id', $roleIds)->get());
            }
        }

        return $recipients;
    }

    /**
     * Department head users for the task's department.
     *
     * @return Collection<int, User>
     */
    private function departmentHeads(Task $task): Collection
    {
        if (! $task->department_id) {
            return collect();
        }

        $headRoleId = $this->roleId(RoleEnum::DepartmentHead);

        if (! $headRoleId) {
            return collect();
        }

        return User::query()
            ->where('role_id', $headRoleId)
            ->whereHas('employee', fn ($q) => $q->where('department_id', $task->department_id))
            ->get();
    }

    private function roleId(RoleEnum $role): ?int
    {
        return Role::query()->where('name', $role->value)->value('id');
    }

    private function alreadySent(User $user, int $taskId, string $type, int $reminderDays): bool
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('task_id', $taskId)
            ->where('reminder_days', $reminderDays)
            ->exists();
    }
}
