<?php

namespace App\Exceptions\TaskManagement;

use RuntimeException;

class InvalidTaskReviewException extends RuntimeException
{
    public static function unauthorized(): self
    {
        return new self('Only authorized reviewers (admin or department head) may perform reviews.');
    }

    public static function selfApproval(): self
    {
        return new self('An employee cannot approve their own submission.');
    }

    public static function invalidState(string $status): self
    {
        return new self("Assignments in status {$status} cannot be reviewed.");
    }

    public static function remarksRequired(): self
    {
        return new self('Remarks are required for every review decision.');
    }
}
