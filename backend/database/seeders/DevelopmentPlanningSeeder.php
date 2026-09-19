<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development annual plans (one per department), the department plan and
 * department targets that operationalize each, and the employee targets that
 * roll up to those department targets.
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") and skips
 * when plans already exist so repeated db:seed runs never duplicate data.
 */
class DevelopmentPlanningSeeder extends Seeder
{
    /**
     * Maps an employee target title to the department target it contributes to.
     *
     * @return array<string, string>
     */
    private function employeeTargetLinks(): array
    {
        return [
            'Complete assigned academic reports' => 'Monthly Faculty Report Completion',
            'Maintain 100% task completion' => 'Student Progress Documentation',
            'Attain on-time report submission rate' => 'Monthly Faculty Report Completion',
            'Compile department academic report' => 'Monthly Faculty Report Completion',
        ];
    }

    /**
     * @return array<int, array{
     *     department_code: string,
     *     title: string,
     *     description: string,
     *     approved: bool,
     *     department_plan_title: string,
     *     department_plan_status: string,
     *     department_targets: array<int, array{name: string, value: float, unit: string, status: string}>,
     *     targets: array<int, array{employee: string, title: string, value: float, unit: string, status: string}>
     * }>
     */
    private function plans(): array
    {
        return [
            [
                'department_code' => 'EDU',
                'title' => 'Faculty Performance and Academic Reporting Plan',
                'description' => 'Development plan for teacher education faculty covering academic reports and instructional deliverables for the 2026-2027 academic year.',
                'approved' => true,
                'department_plan_title' => 'Faculty Documentation and Performance Monitoring Plan',
                'department_plan_status' => 'approved',
                'department_targets' => [
                    ['name' => 'Monthly Faculty Report Completion', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                    ['name' => 'Lesson Plan Submission', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                    ['name' => 'Student Progress Documentation', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0004', 'title' => 'Complete assigned academic reports', 'value' => 12, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0005', 'title' => 'Complete assigned academic reports', 'value' => 12, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0006', 'title' => 'Maintain 100% task completion', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                    ['employee' => 'DEV-0015', 'title' => 'Compile department academic report', 'value' => 10, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0004', 'title' => 'Attain on-time report submission rate', 'value' => 95, 'unit' => 'percent', 'status' => 'active'],
                ],
            ],
            [
                'department_code' => 'CBA',
                'title' => 'Business Faculty Reporting Plan',
                'description' => 'Development plan for business administration faculty focusing on survey and compliance reports.',
                'approved' => false,
                'department_plan_title' => 'Business Faculty Reporting and Compliance Plan',
                'department_plan_status' => 'draft',
                'department_targets' => [
                    ['name' => 'Faculty Development Activities', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0007', 'title' => 'Complete business faculty survey reports', 'value' => 6, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0008', 'title' => 'Submit teaching load compliance reports', 'value' => 4, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0008', 'title' => 'Review course syllabi compliance', 'value' => 12, 'unit' => 'documents', 'status' => 'active'],
                    ['employee' => 'DEV-0007', 'title' => 'Complete accreditation support documents', 'value' => 8, 'unit' => 'documents', 'status' => 'draft'],
                ],
            ],
            [
                'department_code' => 'CIT',
                'title' => 'Information Technology Faculty Reporting Plan',
                'description' => 'Development plan for IT faculty and staff covering research reports and system deliverables.',
                'approved' => false,
                'department_plan_title' => 'IT Reporting and System Deliverables Plan',
                'department_plan_status' => 'draft',
                'department_targets' => [
                    ['name' => 'Academic Compliance Documentation', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0009', 'title' => 'Complete information technology reports', 'value' => 8, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0010', 'title' => 'Deliver dashboard prototypes', 'value' => 2, 'unit' => 'prototypes', 'status' => 'active'],
                    ['employee' => 'DEV-0003', 'title' => 'Deliver IT system documentation', 'value' => 6, 'unit' => 'documents', 'status' => 'active'],
                    ['employee' => 'DEV-0009', 'title' => 'Assist research report compilation', 'value' => 4, 'unit' => 'reports', 'status' => 'draft'],
                ],
            ],
            [
                'department_code' => 'CAS',
                'title' => 'Arts and Sciences Faculty Reporting Plan',
                'description' => 'Development plan for arts and sciences faculty covering event documentation and accomplishment reports.',
                'approved' => false,
                'department_plan_title' => 'Arts and Sciences Documentation Plan',
                'department_plan_status' => 'draft',
                'department_targets' => [
                    ['name' => 'Event Documentation Completion', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0011', 'title' => 'Complete academic event documentation', 'value' => 6, 'unit' => 'documents', 'status' => 'active'],
                    ['employee' => 'DEV-0012', 'title' => 'Submit faculty accomplishment reports', 'value' => 5, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0012', 'title' => 'Complete peer classroom observations', 'value' => 10, 'unit' => 'observations', 'status' => 'active'],
                    ['employee' => 'DEV-0011', 'title' => 'Compile event photo documentation', 'value' => 12, 'unit' => 'albums', 'status' => 'completed'],
                ],
            ],
            [
                'department_code' => 'HR',
                'title' => 'Human Resources Reporting Plan',
                'description' => 'Development plan for HR operations covering attendance and compliance reports.',
                'approved' => true,
                'department_plan_title' => 'HR Attendance and Compliance Reporting Plan',
                'department_plan_status' => 'approved',
                'department_targets' => [
                    ['name' => 'Monthly Attendance Report Completion', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0013', 'title' => 'Complete monthly attendance reports', 'value' => 12, 'unit' => 'reports', 'status' => 'completed'],
                    ['employee' => 'DEV-0013', 'title' => 'Support HR compliance audit', 'value' => 4, 'unit' => 'audits', 'status' => 'active'],
                    ['employee' => 'DEV-0002', 'title' => 'Maintain 100% attendance report accuracy', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
            ],
            [
                'department_code' => 'ADMIN',
                'title' => 'Administrative Operations Plan',
                'description' => 'Development plan for administrative staff covering progress and procurement documentation.',
                'approved' => false,
                'department_plan_title' => 'Administrative Operations Documentation Plan',
                'department_plan_status' => 'draft',
                'department_targets' => [
                    ['name' => 'Administrative Progress Reporting', 'value' => 100, 'unit' => 'percent', 'status' => 'active'],
                ],
                'targets' => [
                    ['employee' => 'DEV-0014', 'title' => 'Complete administrative progress reports', 'value' => 6, 'unit' => 'reports', 'status' => 'active'],
                    ['employee' => 'DEV-0014', 'title' => 'Archive administrative documents', 'value' => 20, 'unit' => 'documents', 'status' => 'draft'],
                    ['employee' => 'DEV-0001', 'title' => 'Publish quarterly operational summary', 'value' => 4, 'unit' => 'reports', 'status' => 'completed'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentPlanningSeeder skipped: development seeding is disabled.');

            return;
        }

        if (AnnualPlan::query()->count() > 0) {
            $this->command?->warn('DevelopmentPlanningSeeder skipped: development plans already exist.');

            return;
        }

        $academicYear = AcademicYear::query()
            ->where('name', config('jpc.academic_year.code'))
            ->firstOrFail();
        $createdBy = User::query()->where('email', 'admin@jpc.local')->value('id');
        $approvedBy = $createdBy;
        $approvedAt = now()->subMonths(3)->startOfMonth();
        $links = $this->employeeTargetLinks();

        DB::transaction(function () use ($academicYear, $createdBy, $approvedBy, $approvedAt, $links) {
            $departments = Department::query()->pluck('id', 'code')->all();
            $employees = Employee::query()->pluck('id', 'employee_number')->all();

            foreach ($this->plans() as $plan) {
                $departmentId = $departments[$plan['department_code']];
                $approved = $plan['approved'];

                $annualPlan = AnnualPlan::updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'department_id' => $departmentId,
                        'title' => $plan['title'],
                    ],
                    [
                        'description' => $plan['description'],
                        'status' => $approved ? 'approved' : 'draft',
                        'created_by' => $createdBy,
                        'approved_by' => $approved ? $approvedBy : null,
                        'approved_at' => $approved ? $approvedAt : null,
                    ],
                );

                $departmentPlan = DepartmentPlan::updateOrCreate(
                    [
                        'annual_plan_id' => $annualPlan->id,
                        'department_id' => $departmentId,
                        'title' => $plan['department_plan_title'],
                    ],
                    [
                        'description' => 'Department-level operational plan derived from the annual plan.',
                        'status' => $plan['department_plan_status'],
                        'created_by' => $createdBy,
                        'approved_by' => $plan['department_plan_status'] === 'approved' ? $approvedBy : null,
                        'approved_at' => $plan['department_plan_status'] === 'approved' ? $approvedAt : null,
                    ],
                );

                $departmentTargets = [];
                foreach ($plan['department_targets'] as $departmentTarget) {
                    $departmentTargets[$departmentTarget['name']] = DepartmentTarget::updateOrCreate(
                        [
                            'department_plan_id' => $departmentPlan->id,
                            'department_id' => $departmentId,
                            'target_name' => $departmentTarget['name'],
                        ],
                        [
                            'target_description' => 'Department target derived from the department plan.',
                            'target_value' => $departmentTarget['value'],
                            'unit' => $departmentTarget['unit'],
                            'start_date' => $academicYear->start_date,
                            'end_date' => $academicYear->end_date,
                            'status' => $departmentTarget['status'],
                            'created_by' => $createdBy,
                        ],
                    );
                }

                foreach ($plan['targets'] as $target) {
                    $departmentTargetId = null;
                    if (isset($links[$target['title']], $departmentTargets[$links[$target['title']]])) {
                        $departmentTargetId = $departmentTargets[$links[$target['title']]]->id;
                    }

                    EmployeeTarget::updateOrCreate(
                        [
                            'annual_plan_id' => $annualPlan->id,
                            'employee_id' => $employees[$target['employee']],
                            'title' => $target['title'],
                        ],
                        [
                            'department_target_id' => $departmentTargetId,
                            'description' => 'Development target derived from the annual plan.',
                            'target_value' => $target['value'],
                            'target_unit' => $target['unit'],
                            'start_date' => $academicYear->start_date,
                            'end_date' => $academicYear->end_date,
                            'status' => $target['status'],
                        ],
                    );
                }
            }
        });
    }
}
