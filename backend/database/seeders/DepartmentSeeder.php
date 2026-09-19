<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds the college/office departments.
 *
 * Idempotent: updateOrCreate() keyed by the unique department code.
 * No real personal information is used here.
 */
class DepartmentSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, description: string}>
     */
    private const DEPARTMENTS = [
        ['code' => 'EDU', 'name' => 'College of Education', 'description' => 'Teacher education programs and faculty.'],
        ['code' => 'CBA', 'name' => 'College of Business Administration', 'description' => 'Business and management programs and faculty.'],
        ['code' => 'CIT', 'name' => 'College of Information Technology', 'description' => 'Information technology programs and faculty.'],
        ['code' => 'CAS', 'name' => 'College of Arts and Sciences', 'description' => 'Arts and sciences programs and faculty.'],
        ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'Employee management, records and development.'],
        ['code' => 'ADMIN', 'name' => 'Administration', 'description' => 'College administrative operations and support services.'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'status' => 'active',
                ],
            );
        }
    }
}
