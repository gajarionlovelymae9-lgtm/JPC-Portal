<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\Enums\NotificationEvent;
use App\Enums\Role;
use App\Models\NotificationPreference;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notification-preference API (§41) + routing-resolution (§89) coverage.
 *
 * @covers App\Services\Notifications\NotificationPreferenceService
 */
class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsUserWith(string $permission): User
    {
        $role = RoleModel::query()
            ->where('name', Role::Employee->value)
            ->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_get_preferences_returns_defaults_for_all_optional_events(): void
    {
        $user = $this->actingAsUserWith('notifications.view');

        NotificationPreference::create([
            'user_id' => $user->id,
            'event' => NotificationEvent::ReportApproved,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.event', NotificationEvent::ReportApproved->value)
            ->assertJsonPath('data.0.channels.in_app', true)
            ->assertJsonPath('data.0.channels.email', true);
    }

    public function test_update_preferences_persists_optional_event_toggle(): void
    {
        $user = $this->actingAsUserWith('notifications.manage');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    NotificationEvent::ReportApproved->value => [
                        'in_app' => false,
                        'email' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'event' => NotificationEvent::ReportApproved->value,
            'in_app_enabled' => false,
            'email_enabled' => true,
        ]);
    }

    public function test_critical_event_cannot_be_disabled_by_preference(): void
    {
        $user = $this->actingAsUserWith('notifications.manage');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    NotificationEvent::RevisionRequired->value => ['in_app' => false, 'email' => false],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'event' => NotificationEvent::RevisionRequired->value,
        ]);
    }

    public function test_unknown_event_is_rejected(): void
    {
        $user = $this->actingAsUserWith('notifications.manage');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => ['not_a_real_event' => ['in_app' => false]],
            ])
            ->assertUnprocessable();
    }

    public function test_preference_is_scoped_to_the_owner(): void
    {
        $owner = $this->actingAsUserWith('notifications.manage');
        NotificationPreference::create([
            'user_id' => $owner->id,
            'event' => NotificationEvent::ReportApproved,
            'in_app_enabled' => false,
            'email_enabled' => true,
        ]);

        $other = $this->actingAsUserWith('notifications.view');
        NotificationPreference::create([
            'user_id' => $other->id,
            'event' => NotificationEvent::ReportApproved,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.channels.in_app', false)
            ->assertJsonPath('data.0.channels.email', true);
    }
}
