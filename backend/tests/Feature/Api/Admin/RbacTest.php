<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, UserSeeder::class]);
    }

    public function test_rbac_endpoint_accessible_by_admin(): void
    {
        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/rbac')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles.admin.label', 'Admin')
            ->assertJsonPath('data.roles.employee.label', 'Employee');
    }

    public function test_rbac_endpoint_forbidden_for_hr(): void
    {
        $this->actingAs($this->user('hr@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/rbac')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_rbac_endpoint_forbidden_for_department_head(): void
    {
        $this->actingAs($this->user('departmenthead@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/rbac')
            ->assertStatus(403);
    }

    public function test_rbac_endpoint_forbidden_for_employee(): void
    {
        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/rbac')
            ->assertStatus(403);
    }

    public function test_rbac_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/rbac')->assertStatus(401);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
