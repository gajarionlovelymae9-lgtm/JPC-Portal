<?php

namespace App\Services\AI\Tools;

use App\Models\User;

/**
 * Optional contract for tools whose answer depends on the caller's role.
 *
 * AiChatService checks this before executing a role-specific tool, so a single
 * intent ("overdue tasks") can resolve to the institution-wide monitoring tool
 * for an administrator and to the employee's own task tool for an employee.
 * Tools that do not implement this interface keep their existing behaviour.
 */
interface RoleAwareAiToolInterface
{
    /**
     * Whether this tool is meaningful and permitted for the given caller.
     */
    public function supports(User $user): bool;
}
