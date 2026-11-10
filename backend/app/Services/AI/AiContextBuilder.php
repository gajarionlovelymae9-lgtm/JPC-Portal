<?php

namespace App\Services\AI;

use App\Models\User;

class AiContextBuilder
{
    public function build(User $user, string $intent, array $toolResult): string
    {
        return json_encode($toolResult);
    }
}
