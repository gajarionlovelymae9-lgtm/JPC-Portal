<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible wrapper used by the Phase 2 test suite.
 *
 * Phase 2 code references Database\Seeders\RolePermissionSeeder directly;
 * the logic now lives in the dedicated RoleSeeder + PermissionSeeder pair from
 * Phase 3.1-D. Keeping this class avoids breaking those references.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
