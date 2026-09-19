<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development employee history records (append-only change log).
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") and skips
 * when history records already exist so repeated db:seed runs never duplicate
 * data.
 *
 * Every record leads to the current state seeded by DevelopmentEmployeeSeeder
 * (employment status / type / department / supervisor transitions, plus some
 * profile-data corrections), attributed to the admin or HR dev account.
 */
class DevelopmentEmployeeHistorySeeder extends Seeder
{
    /**
     * @var array<int, array{
     *     employee: string,
     *     field_name: string,
     *     old_value: string|null,
     *     new_value: string,
     *     effective_date: string,
     *     reason: string,
     *     changed_by: string,
     * }>
     */
    private const HISTORIES = [
        ['employee' => 'DEV-0006', 'field_name' => 'employment_type', 'old_value' => 'probationary', 'new_value' => 'regular', 'effective_date' => '2024-12-05', 'reason' => 'Probation period completed successfully.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0008', 'field_name' => 'department', 'old_value' => 'CAS', 'new_value' => 'CBA', 'effective_date' => '2024-05-01', 'reason' => 'Approved transfer to the College of Business Administration.', 'changed_by' => 'admin@jpc.local'],
        ['employee' => 'DEV-0001', 'field_name' => 'date_of_birth', 'old_value' => '1980-03-10', 'new_value' => '1980-03-12', 'effective_date' => '2024-06-15', 'reason' => 'Corrected date of birth from official documents.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0002', 'field_name' => 'phone', 'old_value' => '0917 555 0002', 'new_value' => '0917 555 0102', 'effective_date' => '2025-03-15', 'reason' => 'Updated contact number on file.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0003', 'field_name' => 'position', 'old_value' => 'IT_STAFF', 'new_value' => 'DEPT_HEAD', 'effective_date' => '2024-06-01', 'reason' => 'Appointed Department Head of the College of Information Technology.', 'changed_by' => 'admin@jpc.local'],
        ['employee' => 'DEV-0004', 'field_name' => 'supervisor', 'old_value' => null, 'new_value' => 'DEV-0015', 'effective_date' => '2025-06-15', 'reason' => 'Assigned to Department Head upon hiring.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0007', 'field_name' => 'supervisor', 'old_value' => null, 'new_value' => 'DEV-0008', 'effective_date' => '2025-08-01', 'reason' => 'Assigned supervising professor within the department.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0010', 'field_name' => 'employment_type', 'old_value' => 'regular', 'new_value' => 'contractual', 'effective_date' => '2025-08-01', 'reason' => 'Contract renewed under a fixed-term arrangement.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0011', 'field_name' => 'address', 'old_value' => '17 Old Address St, Manila', 'new_value' => '17 Arts Street, Humanities Village, Manila', 'effective_date' => '2025-07-15', 'reason' => 'Updated residential address.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0012', 'field_name' => 'gender', 'old_value' => 'm', 'new_value' => 'male', 'effective_date' => '2025-09-10', 'reason' => 'Standardized gender value to the canonical vocabulary.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0013', 'field_name' => 'position', 'old_value' => 'HR_ASSISTANT', 'new_value' => 'HR_OFFICER', 'effective_date' => '2025-06-01', 'reason' => 'Promoted to HR Officer.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0009', 'field_name' => 'employment_status', 'old_value' => 'active', 'new_value' => 'on_leave', 'effective_date' => '2026-09-01', 'reason' => 'Approved leave of absence.', 'changed_by' => 'hr@jpc.local'],
        ['employee' => 'DEV-0014', 'field_name' => 'employment_status', 'old_value' => 'active', 'new_value' => 'inactive', 'effective_date' => '2026-08-31', 'reason' => 'Resignation effective end of the payroll cycle.', 'changed_by' => 'hr@jpc.local'],
    ];

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentEmployeeHistorySeeder skipped: development seeding is disabled.');

            return;
        }

        if (EmployeeHistory::query()->count() > 0) {
            $this->command?->warn('DevelopmentEmployeeHistorySeeder skipped: development history records already exist.');

            return;
        }

        DB::transaction(function () {
            $employees = Employee::query()->pluck('id', 'employee_number')->all();
            $users = User::query()->pluck('id', 'email')->all();

            foreach (self::HISTORIES as $history) {
                EmployeeHistory::firstOrCreate(
                    [
                        'employee_id' => $employees[$history['employee']],
                        'field_name' => $history['field_name'],
                        'effective_date' => $history['effective_date'],
                        'new_value' => $history['new_value'],
                    ],
                    [
                        'old_value' => $history['old_value'],
                        'reason' => $history['reason'],
                        'changed_by' => $users[$history['changed_by']],
                    ],
                );
            }
        });
    }
}
