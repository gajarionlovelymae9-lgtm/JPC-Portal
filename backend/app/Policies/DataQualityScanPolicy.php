<?php

namespace App\Policies;

use App\Models\DataQualityScan;
use App\Models\User;

class DataQualityScanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr', 'department_head']);
    }

    public function view(User $user, DataQualityScan $scan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr', 'department_head']);
    }
}
