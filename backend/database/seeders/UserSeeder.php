<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible wrapper used by the Phase 2 test suite.
 *
 * Phase 2 code references Database\Seeders\UserSeeder directly; the account
 * logic now lives in DevelopmentUserSeeder from Phase 3.1-D. Keeping this
 * class avoids breaking those references.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DevelopmentUserSeeder::class);
    }
}
