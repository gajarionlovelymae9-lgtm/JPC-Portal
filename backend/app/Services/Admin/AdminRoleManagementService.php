<?php

namespace App\Services\Admin;

use App\Enums\Permissions;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Roles & Access (People Management tab).
 *
 * Read-only catalogue of the existing RBAC configuration plus one guarded
 * write: replacing a non-admin role's permission set. The Administrator role
 * is immutable and a fixed deny-list of admin-authority permissions can never
 * be attached to another role — those two guards exist so "Manage Roles" can
 * never accidentally recreate admin access on a lesser role.
 */
class AdminRoleManagementService
{
    /**
     * Permissions that grant authority over administrative surfaces (accounts,
     * roles, permission sets, system settings). They stay admin-only.
     *
     * @var list<string>
     */
    private const ELEVATED_PERMISSIONS = [
        'users.create',
        'users.update',
        'users.delete',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.manage',
        'settings.manage',
    ];

    public function __construct(private readonly ActivityLogger $activity) {}

    /* ==================================================================== */
    /* Reads */
    /* ==================================================================== */

    /**
     * Full catalogue: every role with its assigned permissions, user counts,
     * and the system permission catalog untouched from the Permissions enum.
     *
     * @return array<string, mixed>
     */
    public function catalogue(): array
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        // Counted from `users.role_id`, matching the actual login resolution.
        $userCounts = DB::table('users')
            ->select('role_id')
            ->selectRaw('count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? (RoleEnum::tryFrom($role->name)?->label() ?? $role->name),
                'description' => $role->description,
                /**
                 * The Administrator role is read-only: its permission set is
                 * the system invariant, so the UI must not offer editing it.
                 */
                'is_locked' => $role->name === RoleEnum::Admin->value,
                'users_count' => (int) ($userCounts[$role->id] ?? 0),
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->all(),
            'permissions' => self::permissionCatalog(),
            'total_permissions' => count(Permissions::all()),
            'elevated_permissions' => self::ELEVATED_PERMISSIONS,
        ];
    }

    /* ==================================================================== */
    /* Writes */
    /* ==================================================================== */

    /**
     * Replace a role's permission set in one transaction.
     *
     * @param  list<string>  $permissionNames
     * @return array<string, mixed> the refreshed catalogue
     */
    public function syncPermissions(Role $role, array $permissionNames, ?User $actor = null): array
    {
        if ($role->name === RoleEnum::Admin->value) {
            throw ValidationException::withMessages([
                'role' => 'Administrator permissions are fixed and cannot be edited.',
            ]);
        }

        $duplicates = array_values(array_intersect(self::ELEVATED_PERMISSIONS, $permissionNames));

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'These permissions are reserved for the Administrator role: '.implode(', ', $duplicates).'.',
            ]);
        }

        $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id');
        $known = Permission::query()->whereIn('name', $permissionNames)->pluck('name');

        if ($known->count() !== count($permissionNames)) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more selected permissions no longer exist.',
            ]);
        }

        DB::transaction(function () use ($role, $permissionIds, $permissionNames, $actor): void {
            $previous = $role->permissions()->pluck('name')->sort()->values()->all();
            $next = collect($permissionNames)->sort()->values()->all();

            if ($previous === $next) {
                return;
            }

            $role->permissions()->sync($permissionIds);

            $granted = array_values(array_diff($next, $previous));
            $revoked = array_values(array_diff($previous, $next));

            $this->activity->log(
                activityType: 'admin.role.permissions.update',
                description: sprintf(
                    'Updated permissions for the %s role (%d granted, %d revoked).',
                    $role->label ?? $role->name,
                    count($granted),
                    count($revoked),
                ),
                user: $actor,
                subject: $role,
                metadata: [
                    'granted' => $granted,
                    'revoked' => $revoked,
                    'before' => $previous,
                    'after' => $next,
                ],
            );
        });

        return $this->catalogue();
    }

    /**
     * The permission catalog grouped by module, straight from the enum — the
     * single source of truth for what a role may legitimately receive.
     *
     * @return list<array{group: string, label: string, permissions: list<array{name: string, label: string, elevated: bool}>}>
     */
    private static function permissionCatalog(): array
    {
        $elevated = self::ELEVATED_PERMISSIONS;

        return collect(Permissions::catalog())
            ->map(fn (array $group, string $key) => [
                'group' => $key,
                'label' => self::groupLabel($key),
                'permissions' => collect($group)
                    ->map(fn (string $label, string $name) => [
                        'name' => $name,
                        'label' => $label,
                        'elevated' => in_array($name, $elevated, true),
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private static function groupLabel(string $key): string
    {
        $acronyms = [
            'hr' => 'HR',
            'ocr' => 'OCR',
            'ai.analysis' => 'AI Analysis',
            'ai.summary' => 'AI Summary',
            'ai.validation' => 'AI Validation',
            'ai.similarity' => 'AI Similarity',
        ];

        return $acronyms[$key] ?? ucfirst(str_replace(['.', '_'], ' ', $key));
    }
}
