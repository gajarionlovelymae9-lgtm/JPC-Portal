<?php

namespace App\Services\AI\Tools;

use App\Models\User;

interface AiToolInterface
{
    public function name(): string;

    public function description(): string;

    public function parameters(): array;

    public function requiredPermission(): ?string;

    public function execute(array $params, User $user): array;
}
