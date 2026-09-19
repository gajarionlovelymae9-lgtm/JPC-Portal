<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function employeeUser(Department $department): User
    {
        $role = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function departmentHeadUser(Department $department): User
    {
        $role = RoleModel::query()->where('name', Role::DepartmentHead->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function assignmentDueIn(Department $department, User $employeeUser, int $days): TaskAssignment
    {
        $task = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'in_progress',
            'due_date' => $days === 0
                ? now()->startOfDay()
                : now()->addDays($days)->startOfDay(),
        ]);
        $employee = Employee::query()->where('user_id', $employeeUser->id)->firstOrFail();

        return TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'in_progress',
            'due_date' => $task->due_date,
        ]);
    }

    private function remindersFor(User $user, int $days): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', 'deadline_reminder')
            ->where('reminder_days', $days)
            ->count();
    }

    public function test_deadline_reminders_respect_configured_thresholds(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->employeeUser($dept);

        $this->assignmentDueIn($dept, $employee, 0);
        $this->assignmentDueIn($dept, $employee, 1);
        $this->assignmentDueIn($dept, $employee, 3);
        $this->assignmentDueIn($dept, $employee, 7);
        $this->assignmentDueIn($dept, $employee, 10); // outside [7,3,1,0] -> nothing

        $this->artisan('tasks:send-deadline-reminders')->assertSuccessful();

        $this->assertSame(1, $this->remindersFor($employee, 0));
        $this->assertSame(1, $this->remindersFor($employee, 1));
        $this->assertSame(1, $this->remindersFor($employee, 3));
        $this->assertSame(1, $this->remindersFor($employee, 7));
        $this->assertSame(4, Notification::query()->where('user_id', $employee->id)->count());
    }

    public function test_deadline_reminder_rerun_does_not_duplicate(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->employeeUser($dept);
        $this->assignmentDueIn($dept, $employee, 3);

        $this->artisan('tasks:send-deadline-reminders')->assertSuccessful();
        $this->artisan('tasks:send-deadline-reminders')->assertSuccessful();

        $this->assertSame(1, $this->remindersFor($employee, 3));
    }

    public function test_completed_and_overdue_assignments_get_no_reminders(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->employeeUser($dept);
        $this->assignmentDueIn($dept, $employee, 3);

        // A completed assignment due in the window is not eligible.
        $completedTask = Task::factory()->create([
            'department_id' => $dept->id,
            'status' => 'completed',
            'due_date' => now()->addDays(3)->startOfDay(),
        ]);
        $employeeModel = Employee::query()->where('user_id', $employee->id)->firstOrFail();
        TaskAssignment::factory()->create([
            'employee_id' => $employeeModel->id,
            'task_id' => $completedTask->id,
            'status' => 'completed',
            'completed_at' => now(),
            'due_date' => $completedTask->due_date,
        ]);

        // An overdue assignment is handled by the overdue command instead.
        Task::factory()->create([
            'department_id' => $dept->id,
            'status' => 'in_progress',
            'due_date' => now()->subDays(2)->startOfDay(),
        ])->assignments()->create([
            'employee_id' => $employeeModel->id,
            'assigned_by' => $employee->id,
            'assigned_at' => now()->subDays(10),
            'status' => 'in_progress',
            'due_date' => now()->subDays(2)->startOfDay(),
        ]);

        $this->artisan('tasks:send-deadline-reminders')->assertSuccessful();

        $this->assertSame(1, $this->remindersFor($employee, 3));
        $this->assertSame(0, $this->remindersFor($employee, 2));
    }

    public function test_overdue_command_notifies_employee_and_management_once(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->employeeUser($dept);
        $head = $this->departmentHeadUser($dept);

        Task::factory()->create([
            'department_id' => $dept->id,
            'status' => 'in_progress',
            'due_date' => Carbon::now()->subDays(2)->startOfDay(),
        ])->assignments()->create([
            'employee_id' => Employee::query()->where('user_id', $employee->id)->firstOrFail()->id,
            'assigned_by' => $employee->id,
            'assigned_at' => Carbon::now()->subDays(10),
            'status' => 'in_progress',
            'due_date' => Carbon::now()->subDays(2)->startOfDay(),
        ]);

        $this->artisan('tasks:send-overdue-notifications')->assertSuccessful();
        $this->artisan('tasks:send-overdue-notifications')->assertSuccessful();

        $overdueFor = fn (User $user): int => Notification::query()
            ->where('user_id', $user->id)
            ->where('type', 'task_overdue')
            ->count();

        $this->assertSame(1, $overdueFor($employee));
        $this->assertSame(1, $overdueFor($head));
    }

    // ── SCHEDULER REGISTRATION ─────────────────────────────────

    public function test_schedule_contains_reminder_commands(): void
    {
        // Running schedule:list boots the console kernel, which loads
        // routes/console.php and populates the Schedule singleton.
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('tasks:send-deadline-reminders', $output);
        $this->assertStringContainsString('tasks:send-overdue-notifications', $output);

        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();

        $deadline = collect($events)->firstWhere(fn ($e) => str_contains((string) $e->command, 'tasks:send-deadline-reminders'));
        $overdue = collect($events)->firstWhere(fn ($e) => str_contains((string) $e->command, 'tasks:send-overdue-notifications'));

        $this->assertNotNull($deadline);
        $this->assertNotNull($overdue);
        $this->assertSame('0 7 * * *', $deadline->expression);
        $this->assertSame('30 7 * * *', $overdue->expression);
    }
}