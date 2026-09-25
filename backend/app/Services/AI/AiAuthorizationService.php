<?php
namespace App\Services\AI;
use App\Models\User;

class AiAuthorizationService {
    public function authorize(User $user, string $intent, array $params): array {
        return $params;
    }
}