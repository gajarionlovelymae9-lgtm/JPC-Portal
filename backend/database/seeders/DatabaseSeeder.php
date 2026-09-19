<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Static/structural seeders run first because they are dependencies of
     * the gated development seeders. The development seeders are no-ops on
     * production unless SEED_DEVELOPMENT_DATA is explicitly enabled.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,
            AcademicYearSeeder::class,
            BadgeSeeder::class,
            DevelopmentUserSeeder::class,
            DevelopmentEmployeeSeeder::class,
            DevelopmentEmployeeHistorySeeder::class,
            DevelopmentPlanningSeeder::class,
            DevelopmentTaskSeeder::class,
            DevelopmentTaskActivitySeeder::class,
            DevelopmentReportSeeder::class,
            DevelopmentPerformanceSeeder::class,
            DevelopmentAchievementSeeder::class,
        ]);
    }
}
