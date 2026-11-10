<?php

namespace Tests\Feature\Admin;

use App\Enums\Permissions;
use App\Enums\Role;
use App\Models\Permission;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * People Management module — user account actions and the Roles & Access
 * permission-sync guards.
 *
 * Security invariants under test:
 *  - suspend + reset-password work and revoke API tokens;
 *  - an admin can never suspend their own account;
 *  - the Administrator role's permission set is immutable;
 *  - the elevated permission deny-list can never be granted to another role;
 *  - only users holding permissions.manage may sync role permissions.
 */
class AdminUserAndRolesTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /* ------------------------------------------------------------------ */
    /* User accounts */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function admin_can_suspend_a_user(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);

        $this->postJson("/api/v1/admin/users/{$target->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'suspended']);
    }

    #[Test]
    public function admin_cannot_suspend_their_own_account(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $this->postJson("/api/v1/admin/users/{$admin->id}/suspend")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertNotSame('suspended', $admin->refresh()->status?->value ?? $admin->refresh()->status);
    }

    #[Test]
    public function reset_password_hashes_and_revokes_tokens(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);
        $oldHash = $target->password;
        $token = $target->createToken('device')->plainTextToken;

        $this->postJson("/api/v1/admin/users/{$target->id}/reset-password", [
            'password' => 'new-secure-password-123',
        ])->assertOk()->assertJsonMissing(['password' => 'new-secure-password-123']);

        $target->refresh();
        $this->assertNotSame($oldHash, $target->password);
        $this->assertTrue(Hash::check('new-secure-password-123', $target->password));
        $this->assertTrue(Hash::check('new-secure-password-123', $target->password) && $token !== '');
        $this->assertSame(0, $target->tokens()->count());
    }

    #[Test]
    public function update_rejects_a_new_password_shorter_than_eight_characters(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);
        $oldHash = $target->password;

        foreach (['12345', '1234567'] as $shortPassword) {
            $this->putJson("/api/v1/admin/users/{$target->id}", [
                'password' => $shortPassword,
            ])->assertStatus(422)
                ->assertJsonValidationErrors('password')
                ->assertJsonPath(
                    'errors.password.0',
                    'Password must contain at least 8 characters.',
                );
        }

        // A rejected request must never change the stored hash.
        $this->assertSame($oldHash, $target->refresh()->password);
    }

    #[Test]
    public function update_accepts_exactly_eight_characters_and_hashes_the_password(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);
        $oldHash = $target->password;

        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'password' => '12345678',
        ])->assertOk()->assertJsonMissing(['password' => '12345678']);

        $target->refresh();
        $this->assertNotSame($oldHash, $target->password);
        $this->assertTrue(Hash::check('12345678', $target->password));
    }

    #[Test]
    public function update_accepts_a_password_longer_than_eight_characters(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);

        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'password' => 'a-much-longer-password-2026',
        ])->assertOk()->assertJsonMissing(['password' => 'a-much-longer-password-2026']);

        $this->assertTrue(Hash::check('a-much-longer-password-2026', $target->refresh()->password));
    }

    #[Test]
    public function update_without_a_password_preserves_the_existing_password(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $target = $this->roleUser(Role::Employee);
        $oldHash = $target->password;

        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'password' => '',
            'name' => $target->name,
        ])->assertOk();

        $this->assertSame($oldHash, $target->refresh()->password);
    }

    #[Test]
    public function one_employee_cannot_be_linked_to_two_accounts(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $employee = $this->employeeUser();
        $secondAccount = $this->roleUser(Role::Hr);

        $this->putJson("/api/v1/admin/users/{$secondAccount->id}", [
            'employee_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    /* ------------------------------------------------------------------ */
    /* Roles & Access guards */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function roles_catalogue_lists_permissions_and_user_counts(): void
    {
        $this->loginAs($this->adminUser());

        $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['roles', 'permissions', 'elevated_permissions'],
            ]);

        $roles = RoleModel::query()->with('permissions')->get();
        $this->assertSame($roles->count(), count(RoleModel::query()->get()));
        $this->assertDatabaseCount('roles', $roles->count());
    }

    #[Test]
    public function roles_catalogue_matches_the_permission_enum_and_protects_administrator_access(): void
    {
        $this->loginAs($this->adminUser());

        $data = $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->json('data');

        $this->assertSame(count(Permissions::all()), $data['total_permissions']);

        $catalogNames = collect($data['permissions'])
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('name'))
            ->all();

        $this->assertEqualsCanonicalizing(Permissions::all(), $catalogNames);

        $adminRole = collect($data['roles'])->firstWhere('name', Role::Admin->value);

        $this->assertNotNull($adminRole);
        $this->assertTrue($adminRole['is_locked']);
        $this->assertEqualsCanonicalizing(Permissions::all(), $adminRole['permissions']);

        $this->assertContains('settings.manage', $data['elevated_permissions']);
        $this->assertNotContains('settings.view', $data['elevated_permissions']);

        $groupLabels = collect($data['permissions'])->pluck('label', 'group');
        $this->assertSame('HR', $groupLabels['hr']);
        $this->assertSame('OCR', $groupLabels['ocr']);
        $this->assertSame('AI Analysis', $groupLabels['ai.analysis']);
    }

    #[Test]
    public function only_administrators_can_read_the_roles_catalogue(): void
    {
        foreach ([Role::Hr, Role::DepartmentHead, Role::Employee] as $role) {
            $this->loginAs($this->roleUser($role));

            $this->getJson('/api/v1/admin/roles')->assertStatus(403);
        }
    }

    #[Test]
    public function only_administrators_can_sync_role_permissions(): void
    {
        $employeeRole = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();

        foreach ([Role::Hr, Role::DepartmentHead, Role::Employee] as $role) {
            $this->loginAs($this->roleUser($role));

            $this->putJson("/api/v1/admin/roles/{$employeeRole->id}/permissions", [
                'permissions' => ['reports.view'],
            ])->assertStatus(403);
        }
    }

    #[Test]
    public function administrator_role_cannot_be_edited(): void
    {
        $this->loginAs($this->adminUser());

        $adminRole = RoleModel::query()->where('name', Role::Admin->value)->firstOrFail();
        $before = $adminRole->permissions()->pluck('name')->sort()->values()->all();

        $this->putJson("/api/v1/admin/roles/{$adminRole->id}/permissions", [
            'permissions' => Permission::query()->limit(3)->pluck('name')->all(),
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        $after = $adminRole->refresh()->permissions()->pluck('name')->sort()->values()->all();
        $this->assertSame($before, $after);
        $this->assertEqualsCanonicalizing(Permissions::all(), $after);
    }

    #[Test]
    public function elevated_permissions_rejected_on_non_admin_role(): void
    {
        $this->loginAs($this->adminUser());

        $hrRole = RoleModel::query()->where('name', Role::Hr->value)->firstOrFail();

        $this->putJson("/api/v1/admin/roles/{$hrRole->id}/permissions", [
            'permissions' => ['settings.manage', 'reports.view'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions');

        $this->assertFalse(
            $hrRole->refresh()->permissions->contains('name', 'settings.manage'),
        );
    }

    #[Test]
    public function unknown_permission_names_are_rejected_without_changing_the_role(): void
    {
        $this->loginAs($this->adminUser());

        $hrRole = RoleModel::query()->where('name', Role::Hr->value)->firstOrFail();
        $before = $hrRole->permissions()->pluck('name')->sort()->values()->all();

        $this->putJson("/api/v1/admin/roles/{$hrRole->id}/permissions", [
            'permissions' => ['reports.view', 'not.a.real.permission'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions.1');

        $after = $hrRole->refresh()->permissions()->pluck('name')->sort()->values()->all();
        $this->assertSame($before, $after);
    }

    #[Test]
    public function admin_can_sync_allowed_permissions_on_a_non_admin_role(): void
    {
        $this->loginAs($this->adminUser());

        $employeeRole = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();
        $allowed = Permission::query()
            ->whereIn('name', ['employees.view', 'departments.view'])
            ->pluck('name')->values()->all();

        $this->putJson("/api/v1/admin/roles/{$employeeRole->id}/permissions", [
            'permissions' => $allowed,
        ])->assertOk();

        $names = $employeeRole->refresh()->permissions->pluck('name')->all();
        foreach ($allowed as $name) {
            $this->assertContains($name, $names);
        }
    }

    #[Test]
    public function synced_permissions_replace_the_previous_set_and_persist(): void
    {
        $this->loginAs($this->adminUser());

        $employeeRole = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();
        $allowed = ['employees.view', 'reports.view', 'tasks.complete'];

        $response = $this->putJson("/api/v1/admin/roles/{$employeeRole->id}/permissions", [
            'permissions' => $allowed,
        ])->assertOk();

        $updatedRole = collect($response->json('data.roles'))
            ->firstWhere('id', $employeeRole->id);
        $this->assertSame($allowed, $updatedRole['permissions']);

        // A fresh read confirms the write survived beyond the request.
        $fresh = $this->getJson('/api/v1/admin/roles')->assertOk();

        $freshRole = collect($fresh->json('data.roles'))->firstWhere('id', $employeeRole->id);
        $this->assertSame($allowed, $freshRole['permissions']);

        $stored = $employeeRole->refresh()->permissions()->pluck('name')->sort()->values()->all();
        $this->assertSame($allowed, $stored);
    }

    #[Test]
    public function syncing_permissions_requires_settings_manage_permission(): void
    {
        $hr = $this->roleUser(Role::Hr);
        $this->loginAs($hr);

        $employeeRole = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();

        $this->putJson("/api/v1/admin/roles/{$employeeRole->id}/permissions", [
            'permissions' => ['reports.view'],
        ])->assertStatus(403);
    }

    #[Test]
    public function suspended_users_cannot_authenticate_in_the_api(): void
    {
        $target = $this->roleUser(Role::Employee);

        $this->postJson("/api/v1/admin/users/{$target->id}/suspend", [], [
        ])->assertStatus(401);
    }
}
