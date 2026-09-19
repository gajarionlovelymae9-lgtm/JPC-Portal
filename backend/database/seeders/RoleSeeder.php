<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the four JPC Portal roles.
 *
 * Idempotent: uses updateOrCreate() so repeated runs never duplicate roles.
 * Display names follow the official role naming:
 * Administrator, Human Resources, Department Head, Employee.
 */
class RoleSeeder extends Seeder
{
    /**
     * Display labels required by the JPC Portal role design.
     *
     * @var array<string, string>
     */
    private const DISPLAY_NAMES = [
        RoleEnum::Admin->value => 'Administrator',
        RoleEnum::Hr->value => 'Human Resources',
        RoleEnum::DepartmentHead->value => 'Department Head',
        RoleEnum::Employee->value => 'Employee',
    ];

    /**
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        RoleEnum::Admin->value => 'System administrator with full access to all JPC Portal modules and settings.',
        RoleEnum::Hr->value => 'Manages employee records, performance records and achievement dashboards.',
        RoleEnum::DepartmentHead->value => 'Handles department planning, task assignment, report review and approval for their department.',
        RoleEnum::Employee->value => 'Faculty or staff member who completes assigned tasks and submits reports.',
    ];

    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::updateOrCreate(
                ['name' => $role->value],
                [
                    'label' => self::DISPLAY_NAMES[$role->value],
                    'description' => self::DESCRIPTIONS[$role->value],
                ],
            );
        }
    }
}
