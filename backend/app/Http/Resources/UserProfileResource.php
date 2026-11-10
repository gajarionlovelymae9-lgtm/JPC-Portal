<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe profile payload for the authenticated user. Never exposes the
 * password hash or any internal/sensitive fields.
 */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'status' => $user->status?->value ?? null,
            'role' => $user->roleName(),
            'employee_id' => $user->employee?->id,
            'permissions' => $user->permissionNames(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
