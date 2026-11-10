<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, UserSeeder::class]);
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin'])->get('/api/v1/_test/role', fn () => response()->json(['ok' => true]));

        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->getJson('/api/v1/_test/role')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_role_middleware_allows_any_matching_role_from_list(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin,hr'])->get('/api/v1/_test/role-list', fn () => response()->json(['ok' => true]));

        $this->actingAs($this->user('hr@jpc.local'), 'sanctum')
            ->getJson('/api/v1/_test/role-list')
            ->assertOk();
    }

    public function test_role_middleware_blocks_unmatched_role(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin'])->get('/api/v1/_test/role-blocked', fn () => response()->json(['ok' => true]));

        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->getJson('/api/v1/_test/role-blocked')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_permission_middleware_allows_matching_permission(): void
    {
        Route::middleware(['auth:sanctum', 'permission:reports.review,reports.approve'])->get('/api/v1/_test/perm', fn () => response()->json(['ok' => true]));

        $this->actingAs($this->user('departmenthead@jpc.local'), 'sanctum')
            ->getJson('/api/v1/_test/perm')
            ->assertOk();
    }

    public function test_permission_middleware_blocks_missing_permission(): void
    {
        Route::middleware(['auth:sanctum', 'permission:settings.manage'])->get('/api/v1/_test/perm-blocked', fn () => response()->json(['ok' => true]));

        $this->actingAs($this->user('hr@jpc.local'), 'sanctum')
            ->getJson('/api/v1/_test/perm-blocked')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_group_level_permission_middleware_is_not_overwritten_by_throttle(): void
    {
        // RouteRegistrar::attribute() assigns rather than merges, so two separate
        // ->middleware() calls in one chain silently drop the first. The
        // notifications and messages groups once lost their permission guard to a
        // trailing throttle call; lock both middleware onto those routes.
        foreach ([
            'api/v1/notifications',
            'api/v1/notifications/unread-count',
            'api/v1/messages/conversations',
            'api/v1/messages/recipients',
        ] as $uri) {
            $route = Route::getRoutes()->match(
                Request::create('/'.$uri, 'GET')
            );

            $this->assertContains(
                'permission:notifications.view',
                $route->gatherMiddleware(),
                "Route [{$uri}] must keep its permission guard alongside throttle middleware.",
            );
        }
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
