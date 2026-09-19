<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the four development accounts (one per role).
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") so a
 * production database never receives fake accounts by accident. Passwords come
 * from environment variables (SEED_ADMIN_PASSWORD, ...) with a development-only
 * fallback, and are hashed through the User model's native "hashed" cast.
 *
 * Both users.role_id (Phase 2 compatibility) and the role_user pivot are kept
 * in sync via the Eloquent roles() relationship.
 */
class DevelopmentUserSeeder extends Seeder
{
    /**
     * @var array<int, array{email: string, name: string, role: RoleEnum, config_key: string}>
     */
    private const DEV_USERS = [
        [
            'email' => 'admin@jpc.local',
            'name' => 'JPC Administrator',
            'role' => RoleEnum::Admin,
            'config_key' => 'admin',
        ],
        [
            'email' => 'hr@jpc.local',
            'name' => 'JPC HR',
            'role' => RoleEnum::Hr,
            'config_key' => 'hr',
        ],
        [
            'email' => 'departmenthead@jpc.local',
            'name' => 'JPC Department Head',
            'role' => RoleEnum::DepartmentHead,
            'config_key' => 'department_head',
        ],
        [
            'email' => 'employee@jpc.local',
            'name' => 'JPC Employee',
            'role' => RoleEnum::Employee,
            'config_key' => 'employee',
        ],
    ];

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentUserSeeder skipped: development seeding is disabled.');

            return;
        }

        DB::transaction(function () {
            foreach (self::DEV_USERS as $devUser) {
                $role = Role::query()->where('name', $devUser['role']->value)->firstOrFail();

                $user = User::updateOrCreate(
                    ['email' => $devUser['email']],
                    [
                        'name' => $devUser['name'],
                        'password' => config("jpc.dev_passwords.{$devUser['config_key']}"),
                        'role_id' => $role->id,
                        'status' => UserStatus::Active,
                    ],
                );

                $user->roles()->sync([$role->id]);
            }
        });
    }
}
