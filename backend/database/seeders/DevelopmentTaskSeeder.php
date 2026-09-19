<?php

namespace Database\Seeders;

use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development tasks and their assignments across all departments.
 *
 * Covers every canonical task status (draft, assigned, in_progress, submitted,
 * under_review, completed, rejected, cancelled) and every canonical assignment
 * status (assigned, accepted, in_progress, submitted, completed, rejected,
 * cancelled). Statuses follow docs/database/planning-task-management.md §11.
 *
 * Notable scenarios:
 *  - TASK-2026-0002 (Prepare Monthly Faculty Report) is assigned to TWO
 *    employees to prove one task can have multiple assignees.
 *  - TASK-2026-0001 is a parent task with three child tasks.
 *  - Several tasks are past due but not finished (derived "overdue").
 *  - TASK-2026-0023 was completed AFTER its deadline (late completion).
 *  - TASK-2026-0024 was cancelled.
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") and skips
 * when tasks already exist so repeated db:seed runs never duplicate data.
 */
class DevelopmentTaskSeeder extends Seeder
{
    /**
     * @return array<int, array{
     *     code: string,
     *     title: string,
     *     department_code: string,
     *     priority: string,
     *     start: string,
     *     due: string,
     *     status: string,
     *     estimated_hours: float,
     *     required_output: string,
     *     created_by_email: string,
     *     parent_code: string|null,
     *     department_target: string|null,
     *     employee_target: array{employee: string, title: string}|null,
     *     assignments: array<int, array{employee: string, status: string, progress: int, due: string, completed_at: string|null}>
     * }>
     */
    private function tasks(): array
    {
        return [
            /*
             * Parent task with three children (TASK-2026-0002..0004). Created
             * first so child rows can resolve parent_task_id.
             */
            [
                'code' => 'TASK-2026-0024',
                'title' => 'Prepare Monthly Faculty Documentation',
                'department_code' => 'EDU',
                'priority' => 'high',
                'start' => '2026-06-01',
                'due' => '2026-09-30',
                'status' => 'in_progress',
                'estimated_hours' => 24,
                'required_output' => 'Complete monthly faculty documentation package with all supporting files.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => null,
                'assignments' => [],
            ],
            /*
             * Flagship multi-assignment task (spec §17/§18/§56): one task
             * assigned to TWO employees.
             */
            [
                'code' => 'TASK-2026-0001',
                'title' => 'Prepare Monthly Faculty Report',
                'department_code' => 'EDU',
                'priority' => 'high',
                'start' => '2026-06-01',
                'due' => '2026-06-30',
                'status' => 'in_progress',
                'estimated_hours' => 8,
                'required_output' => 'Monthly faculty report with narrative and supporting tables.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => ['employee' => 'DEV-0004', 'title' => 'Complete assigned academic reports'],
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'completed', 'progress' => 100, 'due' => '2026-06-30', 'completed_at' => '2026-06-25'],
                    ['employee' => 'DEV-0005', 'status' => 'in_progress', 'progress' => 65, 'due' => '2026-06-30', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0002',
                'title' => 'Prepare Faculty Report',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-10-15',
                'status' => 'assigned',
                'estimated_hours' => 4,
                'required_output' => 'Faculty report summary derived from the monthly documentation.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => 'TASK-2026-0024',
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0005', 'status' => 'assigned', 'progress' => 0, 'due' => '2026-10-15', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0003',
                'title' => 'Prepare Attendance Summary',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-10-18',
                'status' => 'in_progress',
                'estimated_hours' => 4,
                'required_output' => 'Attendance summary derived from the faculty attendance log.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => 'TASK-2026-0024',
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0005', 'status' => 'accepted', 'progress' => 10, 'due' => '2026-10-18', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0004',
                'title' => 'Prepare Supporting Documents',
                'department_code' => 'EDU',
                'priority' => 'low',
                'start' => '2026-06-01',
                'due' => '2026-10-20',
                'status' => 'in_progress',
                'estimated_hours' => 6,
                'required_output' => 'Supporting documents and annexes referenced by the report.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => 'TASK-2026-0024',
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'accepted', 'progress' => 10, 'due' => '2026-10-20', 'completed_at' => null],
                ],
            ],
            /*
             * Report-linked tasks (titles must exist — DevelopmentReportSeeder
             * resolves tasks by exact title).
             */
            [
                'code' => 'TASK-2026-0005',
                'title' => 'Submit Faculty Accomplishment Report',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-07-15',
                'status' => 'completed',
                'estimated_hours' => 10,
                'required_output' => 'Faculty accomplishment report with supporting narratives.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => ['employee' => 'DEV-0004', 'title' => 'Complete assigned academic reports'],
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'completed', 'progress' => 100, 'due' => '2026-07-15', 'completed_at' => '2026-07-10'],
                ],
            ],
            [
                'code' => 'TASK-2026-0006',
                'title' => 'Submit Student Progress Report',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-08-01',
                'due' => '2026-09-10',
                'status' => 'completed',
                'estimated_hours' => 8,
                'required_output' => 'Student progress report for each handled class.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Student Progress Documentation',
                'employee_target' => ['employee' => 'DEV-0006', 'title' => 'Maintain 100% task completion'],
                'assignments' => [
                    ['employee' => 'DEV-0006', 'status' => 'completed', 'progress' => 100, 'due' => '2026-09-10', 'completed_at' => '2026-09-05'],
                ],
            ],
            [
                'code' => 'TASK-2026-0007',
                'title' => 'Submit Research Progress Report',
                'department_code' => 'CIT',
                'priority' => 'high',
                'start' => '2026-06-15',
                'due' => '2026-08-10',
                'status' => 'in_progress',
                'estimated_hours' => 12,
                'required_output' => 'Research progress report covering the current cycle.',
                'created_by_email' => 'departmenthead@jpc.local',
                'parent_code' => null,
                'department_target' => 'Academic Compliance Documentation',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0009', 'status' => 'in_progress', 'progress' => 60, 'due' => '2026-08-10', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0008',
                'title' => 'Submit Business Faculty Survey Report',
                'department_code' => 'CBA',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-08-15',
                'status' => 'rejected',
                'estimated_hours' => 9,
                'required_output' => 'Business faculty survey report with analysis.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Faculty Development Activities',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0007', 'status' => 'rejected', 'progress' => 100, 'due' => '2026-08-15', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0009',
                'title' => 'Submit Monthly Attendance Report',
                'department_code' => 'HR',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-07-05',
                'status' => 'completed',
                'estimated_hours' => 6,
                'required_output' => 'Monthly attendance report for all personnel.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Attendance Report Completion',
                'employee_target' => ['employee' => 'DEV-0013', 'title' => 'Complete monthly attendance reports'],
                'assignments' => [
                    ['employee' => 'DEV-0013', 'status' => 'completed', 'progress' => 100, 'due' => '2026-07-05', 'completed_at' => '2026-07-02'],
                ],
            ],
            [
                'code' => 'TASK-2026-0010',
                'title' => 'Submit Curriculum Development Report',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-07-20',
                'status' => 'submitted',
                'estimated_hours' => 8,
                'required_output' => 'Curriculum development report with proposed revisions.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0006', 'status' => 'submitted', 'progress' => 100, 'due' => '2026-07-20', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0011',
                'title' => 'Deploy IT Analytics Dashboard Prototype',
                'department_code' => 'CIT',
                'priority' => 'urgent',
                'start' => '2026-07-01',
                'due' => '2026-09-30',
                'status' => 'completed',
                'estimated_hours' => 40,
                'required_output' => 'Working analytics dashboard prototype and documentation.',
                'created_by_email' => 'departmenthead@jpc.local',
                'parent_code' => null,
                'department_target' => 'Academic Compliance Documentation',
                'employee_target' => ['employee' => 'DEV-0010', 'title' => 'Deliver dashboard prototypes'],
                'assignments' => [
                    ['employee' => 'DEV-0010', 'status' => 'completed', 'progress' => 100, 'due' => '2026-09-30', 'completed_at' => '2026-09-01'],
                ],
            ],
            [
                'code' => 'TASK-2026-0012',
                'title' => 'Prepare Academic Event Documentation',
                'department_code' => 'CAS',
                'priority' => 'low',
                'start' => '2026-09-01',
                'due' => '2026-11-10',
                'status' => 'assigned',
                'estimated_hours' => 6,
                'required_output' => 'Event documentation pack (program, minutes, photos list).',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Event Documentation Completion',
                'employee_target' => ['employee' => 'DEV-0011', 'title' => 'Complete academic event documentation'],
                'assignments' => [
                    ['employee' => 'DEV-0011', 'status' => 'assigned', 'progress' => 0, 'due' => '2026-11-10', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0013',
                'title' => 'Submit College Accomplishment Report',
                'department_code' => 'CAS',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-08-20',
                'status' => 'completed',
                'estimated_hours' => 12,
                'required_output' => 'College-wide accomplishment report for the period.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Event Documentation Completion',
                'employee_target' => ['employee' => 'DEV-0012', 'title' => 'Submit faculty accomplishment reports'],
                'assignments' => [
                    ['employee' => 'DEV-0012', 'status' => 'completed', 'progress' => 100, 'due' => '2026-08-20', 'completed_at' => '2026-08-05'],
                ],
            ],
            [
                'code' => 'TASK-2026-0014',
                'title' => 'Prepare Teaching Load Compliance Report',
                'department_code' => 'CBA',
                'priority' => 'low',
                'start' => '2026-09-01',
                'due' => '2026-12-05',
                'status' => 'draft',
                'estimated_hours' => 5,
                'required_output' => 'Teaching load compliance report.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Faculty Development Activities',
                'employee_target' => null,
                'assignments' => [],
            ],
            [
                'code' => 'TASK-2026-0015',
                'title' => 'Submit Mid-Year Performance Review',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-08-25',
                'status' => 'completed',
                'estimated_hours' => 10,
                'required_output' => 'Mid-year self-performance review document.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Monthly Faculty Report Completion',
                'employee_target' => ['employee' => 'DEV-0004', 'title' => 'Attain on-time report submission rate'],
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'completed', 'progress' => 100, 'due' => '2026-08-25', 'completed_at' => '2026-08-22'],
                ],
            ],
            [
                'code' => 'TASK-2026-0016',
                'title' => 'Conduct IT Training Session',
                'department_code' => 'CIT',
                'priority' => 'low',
                'start' => '2026-08-01',
                'due' => '2026-10-20',
                'status' => 'in_progress',
                'estimated_hours' => 8,
                'required_output' => 'Training session and attendee evaluation report.',
                'created_by_email' => 'departmenthead@jpc.local',
                'parent_code' => null,
                'department_target' => 'Academic Compliance Documentation',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0010', 'status' => 'in_progress', 'progress' => 40, 'due' => '2026-10-20', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0017',
                'title' => 'Submit OBE Course Syllabus',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-06-01',
                'due' => '2026-08-05',
                'status' => 'under_review',
                'estimated_hours' => 7,
                'required_output' => 'Outcomes-based course syllabus aligned to the curriculum.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Lesson Plan Submission',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'submitted', 'progress' => 100, 'due' => '2026-08-05', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0018',
                'title' => 'Prepare Monthly Grade Encoding Summary',
                'department_code' => 'EDU',
                'priority' => 'medium',
                'start' => '2026-08-01',
                'due' => '2026-09-25',
                'status' => 'submitted',
                'estimated_hours' => 5,
                'required_output' => 'Encoded grade summary for the grading period, submitted for review.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Student Progress Documentation',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0004', 'status' => 'submitted', 'progress' => 100, 'due' => '2026-09-25', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0019',
                'title' => 'Prepare Procurement Progress Report',
                'department_code' => 'ADMIN',
                'priority' => 'low',
                'start' => '2026-07-01',
                'due' => '2026-09-20',
                'status' => 'in_progress',
                'estimated_hours' => 6,
                'required_output' => 'Procurement progress report with status of requests.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Administrative Progress Reporting',
                'employee_target' => ['employee' => 'DEV-0014', 'title' => 'Complete administrative progress reports'],
                'assignments' => [
                    ['employee' => 'DEV-0014', 'status' => 'in_progress', 'progress' => 35, 'due' => '2026-09-20', 'completed_at' => null],
                ],
            ],
            /*
             * Overdue and late scenarios.
             */
            [
                'code' => 'TASK-2026-0020',
                'title' => 'Prepare Business Faculty Survey Analysis',
                'department_code' => 'CBA',
                'priority' => 'high',
                'start' => '2026-06-15',
                'due' => '2026-08-15',
                'status' => 'in_progress',
                'estimated_hours' => 10,
                'required_output' => 'Analysis of the business faculty survey responses.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Faculty Development Activities',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0007', 'status' => 'in_progress', 'progress' => 80, 'due' => '2026-08-15', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0021',
                'title' => 'Submit Teaching Load Compliance Report',
                'department_code' => 'CBA',
                'priority' => 'medium',
                'start' => '2026-06-15',
                'due' => '2026-08-10',
                'status' => 'in_progress',
                'estimated_hours' => 8,
                'required_output' => 'Teaching load compliance report for the semester.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Faculty Development Activities',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0008', 'status' => 'in_progress', 'progress' => 40, 'due' => '2026-08-10', 'completed_at' => null],
                ],
            ],
            [
                'code' => 'TASK-2026-0022',
                'title' => 'Submit Academic Compliance Report',
                'department_code' => 'CBA',
                'priority' => 'medium',
                'start' => '2026-07-01',
                'due' => '2026-08-15',
                'status' => 'completed',
                'estimated_hours' => 6,
                'required_output' => 'Academic compliance report covering the current period.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Faculty Development Activities',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0008', 'status' => 'completed', 'progress' => 100, 'due' => '2026-08-15', 'completed_at' => '2026-08-17'],
                ],
            ],
            [
                'code' => 'TASK-2026-0023',
                'title' => 'Prepare Quarterly Procurement Audit',
                'department_code' => 'ADMIN',
                'priority' => 'low',
                'start' => '2026-07-01',
                'due' => '2026-09-30',
                'status' => 'cancelled',
                'estimated_hours' => 5,
                'required_output' => 'Quarterly procurement audit report.',
                'created_by_email' => 'admin@jpc.local',
                'parent_code' => null,
                'department_target' => 'Administrative Progress Reporting',
                'employee_target' => null,
                'assignments' => [
                    ['employee' => 'DEV-0014', 'status' => 'cancelled', 'progress' => 0, 'due' => '2026-09-30', 'completed_at' => null],
                ],
            ],
        ];
    }

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentTaskSeeder skipped: development seeding is disabled.');

            return;
        }

        if (Task::query()->count() > 0) {
            $this->command?->warn('DevelopmentTaskSeeder skipped: development tasks already exist.');

            return;
        }

        DB::transaction(function () {
            $departments = Department::query()->pluck('id', 'code')->all();
            $employees = Employee::query()->pluck('id', 'employee_number')->all();
            $users = User::query()->pluck('id', 'email')->all();
            $annualPlans = AnnualPlan::query()->pluck('id', 'department_id')->all();
            $departmentPlans = DepartmentPlan::query()->pluck('id', 'department_id')->all();
            $departmentTargets = DepartmentTarget::query()->pluck('id', 'target_name')->all();
            $employeeTargets = EmployeeTarget::query()
                ->get(['id', 'employee_id', 'title'])
                ->keyBy(fn (EmployeeTarget $target) => $target->employee_id.'|'.$target->title)
                ->all();

            $taskIds = [];

            foreach ($this->tasks() as $task) {
                $departmentId = $departments[$task['department_code']];
                $createdBy = $users[$task['created_by_email']];

                $employeeTargetId = null;
                if ($task['employee_target'] !== null) {
                    $key = $employees[$task['employee_target']['employee']].'|'.$task['employee_target']['title'];
                    $employeeTargetId = $employeeTargets[$key]->id ?? null;
                }

                $created = Task::updateOrCreate(
                    ['task_code' => $task['code']],
                    [
                        'annual_plan_id' => $annualPlans[$departmentId] ?? null,
                        'department_plan_id' => $departmentPlans[$departmentId] ?? null,
                        'department_target_id' => $task['department_target'] !== null
                            ? ($departmentTargets[$task['department_target']] ?? null)
                            : null,
                        'employee_target_id' => $employeeTargetId,
                        'parent_task_id' => $task['parent_code'] !== null
                            ? ($taskIds[$task['parent_code']] ?? null)
                            : null,
                        'department_id' => $departmentId,
                        'title' => $task['title'],
                        'description' => 'Development task for '.$task['required_output'],
                        'priority' => $task['priority'],
                        'start_date' => $task['start'],
                        'due_date' => $task['due'],
                        'status' => $task['status'],
                        'estimated_hours' => $task['estimated_hours'],
                        'required_output' => $task['required_output'],
                        'created_by' => $createdBy,
                        'updated_by' => $task['status'] === 'draft' ? null : $createdBy,
                        'completed_at' => $this->taskCompletedAt($task),
                    ],
                );

                $taskIds[$task['code']] = $created->id;

                foreach ($task['assignments'] as $assignment) {
                    TaskAssignment::updateOrCreate(
                        [
                            'task_id' => $created->id,
                            'employee_id' => $employees[$assignment['employee']],
                        ],
                        [
                            'assigned_by' => $createdBy,
                            'assigned_at' => $task['start'],
                            'start_date' => $task['start'],
                            'due_date' => $assignment['due'],
                            'progress_percentage' => $assignment['progress'],
                            'status' => $assignment['status'],
                            'completed_at' => $assignment['completed_at'],
                        ],
                    );
                }
            }
        });
    }

    /**
     * Resolve the task-level completion timestamp from its completed assignment.
     *
     * @param  array<string, mixed>  $task
     */
    private function taskCompletedAt(array $task): ?string
    {
        if ($task['status'] !== 'completed') {
            return null;
        }

        foreach ($task['assignments'] as $assignment) {
            if ($assignment['completed_at'] !== null) {
                return $assignment['completed_at'];
            }
        }

        return null;
    }
}
