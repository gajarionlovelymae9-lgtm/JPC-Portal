<?php

namespace App\Exceptions\TaskManagement;

use RuntimeException;

class InvalidProgressException extends RuntimeException
{
    public static function outOfBounds(float $percentage): self
    {
        return new self("Progress percentage must be between 0 and 100; received {$percentage}.");
    }

    public static function backwardProgress(float $from, float $to): self
    {
        return new self("Invalid progress: {$from}% → {$to}%. Backward progress is not allowed.");
    }
}
