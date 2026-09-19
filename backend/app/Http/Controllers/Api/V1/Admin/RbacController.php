<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Permissions;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Demo endpoint exposing the current role -> permission matrix.
 *
 * This only exercises authorization ("permission:settings.manage") and
 * provides a read-only summary; the full RBAC management UI belongs to a
 * later phase.
 */
class RbacController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $matrix = [];
        foreach (Permissions::assignments() as $roleValue => $permissionNames) {
            $matrix[$roleValue] = [
                'label' => RoleEnum::tryFrom($roleValue)?->label() ?? $roleValue,
                'permissions' => $permissionNames,
            ];
        }

        return ApiResponse::success('Role-based access control summary', [
            'roles' => $matrix,
            'catalog' => Permissions::catalog(),
        ]);
    }
}
