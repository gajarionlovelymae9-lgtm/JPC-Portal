<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\PasswordChangeRequest;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeRequestFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createUser(Role $role, string $password = 'P@ssw0rd2026'): User
    {
        $roleRecord = RoleModel::query()->where('name', $role->value)->firstOrFail();
        return User::factory()->create([
            'role_id' => $roleRecord->id,
            'password' => Hash::make($password),
        ]);
    }

    public function test_employee_can_request_password_change_with_valid_current_password(): void
    {
        $employee = $this->createUser(Role::Employee, 'CurrentSecret123!');

        $response = $this->actingAs($employee)->postJson('/api/v1/employee/security/password-requests', [
            'current_password' => 'CurrentSecret123!',
            'password' => 'NewSecretPassword2026!',
            'password_confirmation' => 'NewSecretPassword2026!',
            'reason' => 'Routine security update',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('password_change_requests', [
            'user_id' => $employee->id,
            'status' => 'pending',
            'reason' => 'Routine security update',
        ]);
    }

    public function test_employee_request_fails_if_current_password_is_wrong(): void
    {
        $employee = $this->createUser(Role::Employee, 'CurrentSecret123!');

        $response = $this->actingAs($employee)->postJson('/api/v1/employee/security/password-requests', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewSecretPassword2026!',
            'password_confirmation' => 'NewSecretPassword2026!',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('password_change_requests', [
            'user_id' => $employee->id,
        ]);
    }

    public function test_employee_request_fails_if_confirmation_mismatches(): void
    {
        $employee = $this->createUser(Role::Employee, 'CurrentSecret123!');

        $response = $this->actingAs($employee)->postJson('/api/v1/employee/security/password-requests', [
            'current_password' => 'CurrentSecret123!',
            'password' => 'NewSecretPassword2026!',
            'password_confirmation' => 'DifferentSecret!',
        ]);

        $response->assertStatus(422);
    }

    public function test_employee_can_check_password_change_status(): void
    {
        $employee = $this->createUser(Role::Employee);

        PasswordChangeRequest::create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'proposed_password' => Hash::make('NewPass123!'),
            'reason' => 'Quarterly check',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee)->getJson('/api/v1/employee/security/password-requests');
        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reason', 'Quarterly check');
    }

    public function test_admin_can_list_and_review_password_change_requests(): void
    {
        $admin = $this->createUser(Role::Admin);
        $employee = $this->createUser(Role::Employee);

        $pcr = PasswordChangeRequest::create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'proposed_password' => Hash::make('NewPass123!'),
            'reason' => 'Forgot password',
            'status' => 'pending',
        ]);

        $listResponse = $this->actingAs($admin)->getJson('/api/v1/admin/password-change-requests');
        $listResponse->assertSuccessful();
        $listResponse->assertJsonPath('data.summary.pending', 1);

        $showResponse = $this->actingAs($admin)->getJson("/api/v1/admin/password-change-requests/{$pcr->id}");
        $showResponse->assertSuccessful();
        $showResponse->assertJsonPath('data.id', $pcr->id);
    }

    public function test_admin_approving_request_updates_user_password(): void
    {
        $admin = $this->createUser(Role::Admin);
        $employee = $this->createUser(Role::Employee, 'OldPassword123!');

        $pcr = PasswordChangeRequest::create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'proposed_password' => Hash::make('BrandNewPassword456!'),
            'reason' => 'Please approve',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/password-change-requests/{$pcr->id}/approve", [
            'decision_note' => 'Approved by admin',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'approved');

        $employee->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword456!', $employee->password));
    }

    public function test_admin_rejecting_request_leaves_password_unchanged(): void
    {
        $admin = $this->createUser(Role::Admin);
        $employee = $this->createUser(Role::Employee, 'OldPassword123!');

        $pcr = PasswordChangeRequest::create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'proposed_password' => Hash::make('BrandNewPassword456!'),
            'reason' => 'Please approve',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/password-change-requests/{$pcr->id}/reject", [
            'decision_note' => 'Denied due to suspicious activity',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'rejected');

        $employee->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $employee->password));
    }

    public function test_non_admin_cannot_review_password_change_requests(): void
    {
        $employee = $this->createUser(Role::Employee);

        $response = $this->actingAs($employee)->getJson('/api/v1/admin/password-change-requests');
        $response->assertStatus(403);
    }
}
