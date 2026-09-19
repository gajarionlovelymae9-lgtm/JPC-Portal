<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_PASSWORD = 'P@ssw0rd2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, UserSeeder::class]);

        // Sanctum only boots the session pipeline for "stateful" requests,
        // which are identified by a matching Origin/Referer header — exactly
        // what a real browser sends when calling the SPA.
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@jpc.local',
            'password' => self::DEV_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.email', 'admin@jpc.local')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'status',
                    'role',
                    'permissions',
                    'last_login_at',
                ],
            ]);

        $this->assertDatabaseHas('login_activities', [
            'email' => 'admin@jpc.local',
            'event' => 'login',
        ]);
    }

    public function test_login_returns_profile_permissions(): void
    {
        $user = User::query()->where('email', 'hr@jpc.local')->firstOrFail();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::DEV_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'hr')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.permissions.0', 'dashboard.view');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@jpc.local',
            'password' => 'not-the-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'These credentials do not match our records.');

        $this->assertDatabaseHas('login_activities', [
            'email' => 'admin@jpc.local',
            'event' => 'failed',
        ]);
    }

    public function test_login_rejects_unknown_email_with_same_generic_message(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@jpc.local',
            'password' => self::DEV_PASSWORD,
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'These credentials do not match our records.');
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'inactive@jpc.local',
            'password' => 'secret1234',
            'status' => 'inactive',
            'role_id' => Role::query()->where('name', 'employee')->value('id'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@jpc.local',
            'password' => 'secret1234',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'These credentials do not match our records.');
    }

    public function test_login_rejects_suspended_account(): void
    {
        User::factory()->create([
            'email' => 'suspended@jpc.local',
            'password' => 'secret1234',
            'status' => 'suspended',
            'role_id' => Role::query()->where('name', 'employee')->value('id'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@jpc.local',
            'password' => 'secret1234',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'These credentials do not match our records.');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'admin@jpc.local',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@jpc.local',
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests');
    }

    public function test_full_login_me_logout_session_flow(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@jpc.local',
            'password' => self::DEV_PASSWORD,
        ])->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@jpc.local');

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful');

        // A real browser got a fresh, logged-out cookie. In the test the app
        // lives in one process and caches the resolved guard — reset it so the
        // next request re-reads the (now empty) session, like a new HTTP call.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_me_returns_authenticated_user_profile(): void
    {
        $user = User::query()->where('email', 'employee@jpc.local')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'employee@jpc.local')
            ->assertJsonPath('data.role', 'employee');
    }

    public function test_logout_succeeds_for_authenticated_user(): void
    {
        $user = User::query()->where('email', 'admin@jpc.local')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout successful');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
    }
}
