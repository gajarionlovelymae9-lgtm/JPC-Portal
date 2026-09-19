<?php

namespace App\Exceptions\TaskManagement;

use RuntimeException;

class TaskNotAssignableException extends RuntimeException
{
    public static function employeeNotActive(): self
    {
        return new self('The employee must be active to receive a task assignment.');
    }

    public static function assignmentAlreadyExists(): self
    {
        return new self('This employee already has an assignment for this task.');
    }

    public static function taskNotAssignable(string $status): self
    {
        return new self("Tasks with status {$status} cannot be assigned.");
    }
}
