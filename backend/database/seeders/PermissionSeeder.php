<?php

namespace Database\Seeders;

use App\Enums\Permissions;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the full permission catalog and the default role-to-permission
 * assignments defined in App\Enums\Permissions.
 *
 * The catalog and the matrix live in the enum (the single source of truth
 * also consumed by the RBAC controller). Idempotent: updateOrCreate persists
 * catalog entries and sync() makes assignments deterministic on every run.
 *
 * Dependency: RoleSeeder must run before this seeder for the assignment step.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];
        foreach (Permissions::catalog() as $group => $items) {
            foreach ($items as $name => $label) {
                $permissions[$name] = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'label' => $label,
                        'group' => $group,
                    ],
                );
            }
        }

        $expectedRoles = array_column(RoleEnum::cases(), 'value');
        $existingRoles = Role::query()
            ->whereIn('name', $expectedRoles)
            ->pluck('id', 'name')
            ->all();

        if (count(array_intersect($expectedRoles, array_keys($existingRoles))) !== count($expectedRoles)) {
            $this->command?->warn('PermissionSeeder skipped role assignments: run RoleSeeder first.');

            return;
        }

        foreach (Permissions::assignments() as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $role->permissions()->sync(
                array_map(
                    fn (string $name): int => $permissions[$name]->id,
                    $permissionNames,
                ),
            );
        }
    }
}
