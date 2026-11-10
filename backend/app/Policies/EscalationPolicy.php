<?php

namespace App\Policies;

use App\Models\DepartmentEscalation;
use App\Models\User;

/**
 * Authorization for the escalations module.
 *
 * Department Heads only ever see and act on escalations belonging to their own
 * department, while Administration (the `admin` role already defined by the
 * project) owns every review, assignment, status change and note action.
 */
class EscalationPolicy
{
    /**
     * Whether the user may browse the escalations module at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['department_head', 'admin']);
    }

    /**
     * Whether the user may raise an escalation.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['department_head', 'admin']);
    }

    /**
     * Whether the user may view the given escalation.
     */
    public function view(User $user, DepartmentEscalation $escalation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('department_head')
            && $user->employee?->department_id === $escalation->department_id;
    }

    /**
     * Whether the user may review (change status / record resolution).
     */
    public function review(User $user, DepartmentEscalation $escalation): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Whether the user may assign a responsible administrator.
     */
    public function assign(User $user, DepartmentEscalation $escalation): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Whether the user may add a note to the escalation.
     */
    public function addNote(User $user, DepartmentEscalation $escalation): bool
    {
        return $user->hasRole('admin');
    }
}
