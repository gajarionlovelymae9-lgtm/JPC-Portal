<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard JPC Portal positions.
 *
 * Idempotent: updateOrCreate() keyed by the unique position code.
 */
class PositionSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, description: string}>
     */
    private const POSITIONS = [
        ['code' => 'TEACHER', 'name' => 'Teacher', 'description' => 'Handles classroom instruction and academic reporting.'],
        ['code' => 'INSTRUCTOR', 'name' => 'Instructor', 'description' => 'Provides instruction and academic support.'],
        ['code' => 'PROFESSOR', 'name' => 'Professor', 'description' => 'Senior faculty handling instruction and research.'],
        ['code' => 'DEPT_HEAD', 'name' => 'Department Head', 'description' => 'Leads a department and manages its faculty.'],
        ['code' => 'HR_OFFICER', 'name' => 'HR Officer', 'description' => 'Handles day-to-day human resources operations.'],
        ['code' => 'HR_MANAGER', 'name' => 'HR Manager', 'description' => 'Oversees human resources operations and records.'],
        ['code' => 'DEAN', 'name' => 'Dean', 'description' => 'Oversees a college and its academic programs.'],
        ['code' => 'ADMIN_STAFF', 'name' => 'Administrative Staff', 'description' => 'Supports administrative and office operations.'],
        ['code' => 'IT_STAFF', 'name' => 'IT Staff', 'description' => 'Supports information technology systems and services.'],
        ['code' => 'REGISTRAR', 'name' => 'Registrar', 'description' => 'Manages academic records and enrollment.'],
        ['code' => 'SYS_ADMIN', 'name' => 'System Administrator', 'description' => 'Administers the JPC Portal and related systems.'],
    ];

    public function run(): void
    {
        foreach (self::POSITIONS as $position) {
            Position::updateOrCreate(
                ['code' => $position['code']],
                [
                    'name' => $position['name'],
                    'description' => $position['description'],
                    'status' => 'active',
                ],
            );
        }
    }
}
