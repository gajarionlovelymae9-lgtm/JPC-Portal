<?php

namespace App\Exceptions\TaskManagement;

use RuntimeException;

class InvalidTaskStatusTransitionException extends RuntimeException
{
    public static function from(string $from, string $to): self
    {
        return new self("Invalid status transition: {$from} → {$to}.");
    }
}
