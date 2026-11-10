<?php

namespace Tests\Feature\Database;

use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 — Role & permission baseline seeder tests.
 *
 * The RolePermissionSeeder (Role + Permission) is the authorisation bedrock
 * every request depends on. If the seed changes shape silently, feature tests
 * keep working against factories but the real first-boot experience breaks.
 * These tests lock the canonical role set and the key permission names the
 * middleware and services authorize against.
 */
class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seed_creates_the_four_canonical_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $names = Role::pluck('name')->all();
        foreach (RoleEnum::cases() as $role) {
            $this->assertContains($role->value, $names, "Role [{$role->value}] missing after seed.");
        }
    }

    #[Test]
    public function seed_populates_the_permission_catalog(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertGreaterThan(50, Permission::count());
        $this->assertDatabaseHas('permissions', ['name' => 'reports.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'analytics.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'settings.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'ocr.view']);
    }

    #[Test]
    public function admin_has_full_settings_access(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Admin);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasPermission('settings.manage'));
        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertTrue($user->hasPermission('analytics.view'));
    }

    #[Test]
    public function employee_has_reports_view_but_not_analytics_view(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Employee);

        $this->assertTrue($user->hasRole('employee'));
        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertTrue($user->hasPermission('reports.create'));
        $this->assertFalse($user->hasPermission('analytics.view'));
        $this->assertFalse($user->hasPermission('settings.manage'));
    }

    #[Test]
    public function department_head_role_is_permitted_and_scope_locked(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::DepartmentHead);

        // The canonical role name must stay 'department_head' — policies and
        // middleware reference it; 'dept_head' would silently break them.
        $this->assertTrue($user->hasRole('department_head'));
        $this->assertFalse($user->hasRole('dept_head'));

        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertTrue($user->hasPermission('reports.review'));
        $this->assertTrue($user->hasPermission('analytics.view'));
        // Department heads do not get employee-only submission perms.
        $this->assertFalse($user->hasPermission('reports.submit'));
    }

    #[Test]
    public function hr_has_analytics_but_no_system_admin_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Hr);

        $this->assertTrue($user->hasRole('hr'));
        $this->assertTrue($user->hasPermission('analytics.view'));
        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('settings.manage'));
    }
}
