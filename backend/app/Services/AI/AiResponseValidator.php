<?php

namespace App\Services\AI;

class AiResponseValidator
{
    public function validate(string $response, array $toolResult): array
    {
        return ['valid' => true, 'sanitized' => $response, 'warnings' => []];
    }
}
