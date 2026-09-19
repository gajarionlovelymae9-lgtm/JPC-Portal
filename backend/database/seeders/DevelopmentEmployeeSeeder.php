<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development employee records spread across all departments.
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") and skips
 * when employees already exist so repeated db:seed runs never duplicate data.
 * All names are fake; no real personal information is used.
 *
 * Phase 3.2-D enriches the records with:
 *   - employee profile data (gender, date of birth, phone, address)
 *   - employment type variety (regular / probationary / contractual)
 *   - employment status variety (active / on_leave / inactive)
 *   - a supervisor hierarchy (department heads supervise their members)
 *   - a login account for every employee (the four canonical role accounts
 *     plus deterministic dev-XXXX accounts for the remaining employees)
 */
class DevelopmentEmployeeSeeder extends Seeder
{
    /**
     * Fake development employees. user_email links a canonical dev account;
     * a deterministic dev-XXXX account is created for every other employee.
     *
     * @var array<int, array{
     *     employee_number: string,
     *     first_name: string,
     *     last_name: string,
     *     user_email: string|null,
     *     position_code: string,
     *     department_code: string,
     *     date_hired: string,
     *     employment_status: string,
     *     employment_type: string,
     *     gender: string,
     *     date_of_birth: string,
     *     phone: string,
     *     address: string,
     * }>
     */
    private const EMPLOYEES = [
        ['employee_number' => 'DEV-0001', 'first_name' => 'JPC', 'last_name' => 'Administrator', 'user_email' => 'admin@jpc.local', 'position_code' => 'SYS_ADMIN', 'department_code' => 'ADMIN', 'date_hired' => '2024-01-15', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'male', 'date_of_birth' => '1980-03-12', 'phone' => '0917 555 0101', 'address' => '123 Sample St, University District, Manila'],
        ['employee_number' => 'DEV-0002', 'first_name' => 'JPC', 'last_name' => 'HR', 'user_email' => 'hr@jpc.local', 'position_code' => 'HR_MANAGER', 'department_code' => 'HR', 'date_hired' => '2024-03-10', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'female', 'date_of_birth' => '1986-07-21', 'phone' => '0917 555 0102', 'address' => '245 Sample Rd, Employee Village, Quezon City'],
        ['employee_number' => 'DEV-0003', 'first_name' => 'JPC', 'last_name' => 'Department Head', 'user_email' => 'departmenthead@jpc.local', 'position_code' => 'DEPT_HEAD', 'department_code' => 'CIT', 'date_hired' => '2023-06-01', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'male', 'date_of_birth' => '1978-11-05', 'phone' => '0917 555 0103', 'address' => '78 Tech Avenue, ICT District, Calamba'],
        ['employee_number' => 'DEV-0004', 'first_name' => 'JPC', 'last_name' => 'Employee', 'user_email' => 'employee@jpc.local', 'position_code' => 'TEACHER', 'department_code' => 'EDU', 'date_hired' => '2025-06-01', 'employment_status' => 'active', 'employment_type' => 'probationary', 'gender' => 'female', 'date_of_birth' => '1996-02-18', 'phone' => '0917 555 0104', 'address' => '31 Education Lane, Teacher Village, Laguna'],
        ['employee_number' => 'DEV-0005', 'first_name' => 'Carlo', 'last_name' => 'Sample', 'user_email' => null, 'position_code' => 'TEACHER', 'department_code' => 'EDU', 'date_hired' => '2025-06-10', 'employment_status' => 'active', 'employment_type' => 'probationary', 'gender' => 'male', 'date_of_birth' => '1993-09-02', 'phone' => '0917 555 0105', 'address' => '14 Sample Drive, Education Village, Sta. Rosa'],
        ['employee_number' => 'DEV-0006', 'first_name' => 'Dara', 'last_name' => 'Sample', 'user_email' => null, 'position_code' => 'TEACHER', 'department_code' => 'EDU', 'date_hired' => '2024-06-05', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'female', 'date_of_birth' => '1994-04-25', 'phone' => '0917 555 0106', 'address' => '22 Sample St, Teacher Village, Biñan'],
        ['employee_number' => 'DEV-0007', 'first_name' => 'Bea', 'last_name' => 'Villar', 'user_email' => null, 'position_code' => 'INSTRUCTOR', 'department_code' => 'CBA', 'date_hired' => '2025-06-15', 'employment_status' => 'active', 'employment_type' => 'probationary', 'gender' => 'female', 'date_of_birth' => '1997-08-11', 'phone' => '0917 555 0107', 'address' => '8 Business Row, Commerce District, Manila'],
        ['employee_number' => 'DEV-0008', 'first_name' => 'Fred', 'last_name' => 'Cruz', 'user_email' => null, 'position_code' => 'PROFESSOR', 'department_code' => 'CBA', 'date_hired' => '2023-08-01', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'male', 'date_of_birth' => '1972-12-30', 'phone' => '0917 555 0108', 'address' => '101 Commerce Avenue, Santa Rosa, Laguna'],
        ['employee_number' => 'DEV-0009', 'first_name' => 'Hana', 'last_name' => 'Reyes', 'user_email' => null, 'position_code' => 'INSTRUCTOR', 'department_code' => 'CIT', 'date_hired' => '2025-06-12', 'employment_status' => 'on_leave', 'employment_type' => 'probationary', 'gender' => 'female', 'date_of_birth' => '1995-10-14', 'phone' => '0917 555 0109', 'address' => '45 ICT Street, Digital Village, Calamba'],
        ['employee_number' => 'DEV-0010', 'first_name' => 'Ian', 'last_name' => 'Torres', 'user_email' => null, 'position_code' => 'IT_STAFF', 'department_code' => 'CIT', 'date_hired' => '2024-02-01', 'employment_status' => 'active', 'employment_type' => 'contractual', 'gender' => 'male', 'date_of_birth' => '1991-06-19', 'phone' => '0917 555 0110', 'address' => 'Software Park Compound, ICT District, Manila'],
        ['employee_number' => 'DEV-0011', 'first_name' => 'Ella', 'last_name' => 'Bautista', 'user_email' => null, 'position_code' => 'INSTRUCTOR', 'department_code' => 'CAS', 'date_hired' => '2025-06-08', 'employment_status' => 'active', 'employment_type' => 'probationary', 'gender' => 'female', 'date_of_birth' => '1996-01-07', 'phone' => '0917 555 0111', 'address' => '17 Arts Street, Humanities Village, Manila'],
        ['employee_number' => 'DEV-0012', 'first_name' => 'Jay', 'last_name' => 'Mendoza', 'user_email' => null, 'position_code' => 'PROFESSOR', 'department_code' => 'CAS', 'date_hired' => '2023-06-12', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'male', 'date_of_birth' => '1970-05-22', 'phone' => '0917 555 0112', 'address' => '85 Cas Lane, Arts District, Quezon City'],
        ['employee_number' => 'DEV-0013', 'first_name' => 'Gina', 'last_name' => 'Navarro', 'user_email' => null, 'position_code' => 'HR_OFFICER', 'department_code' => 'HR', 'date_hired' => '2024-05-20', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'female', 'date_of_birth' => '1990-03-03', 'phone' => '0917 555 0113', 'address' => '9 HR Complex, Employee Village, Pasig'],
        ['employee_number' => 'DEV-0014', 'first_name' => 'Ana', 'last_name' => 'Santos', 'user_email' => null, 'position_code' => 'ADMIN_STAFF', 'department_code' => 'ADMIN', 'date_hired' => '2025-01-05', 'employment_status' => 'inactive', 'employment_type' => 'regular', 'gender' => 'female', 'date_of_birth' => '1998-11-29', 'phone' => '0917 555 0114', 'address' => '60 Admin Boulevard, University District, Manila'],
        ['employee_number' => 'DEV-0015', 'first_name' => 'Mark', 'last_name' => 'Dela Cruz', 'user_email' => null, 'position_code' => 'DEPT_HEAD', 'department_code' => 'EDU', 'date_hired' => '2022-06-06', 'employment_status' => 'active', 'employment_type' => 'regular', 'gender' => 'male', 'date_of_birth' => '1979-09-16', 'phone' => '0917 555 0115', 'address' => '5 Education Avenue, Teacher Village, San Pedro'],
    ];

    /**
     * Supervisor hierarchy keyed by employee number. Department heads
     * supervise members of their department; senior faculty supervise peers
     * in departments without a head. Null = top of the chain.
     *
     * @var array<string, string|null>
     */
    private const SUPERVISORS = [
        'DEV-0001' => null,
        'DEV-0002' => 'DEV-0001',
        'DEV-0003' => null,
        'DEV-0004' => 'DEV-0015',
        'DEV-0005' => 'DEV-0015',
        'DEV-0006' => 'DEV-0015',
        'DEV-0007' => 'DEV-0008',
        'DEV-0008' => null,
        'DEV-0009' => 'DEV-0003',
        'DEV-0010' => 'DEV-0003',
        'DEV-0011' => 'DEV-0012',
        'DEV-0012' => null,
        'DEV-0013' => 'DEV-0002',
        'DEV-0014' => 'DEV-0001',
        'DEV-0015' => null,
    ];

    /**
     * Current department heads: [employee_number, department_code, start_date].
     *
     * @var array<int, array{employee_number: string, department_code: string, start_date: string}>
     */
    private const DEPARTMENT_HEADS = [
        ['employee_number' => 'DEV-0003', 'department_code' => 'CIT', 'start_date' => '2024-06-01'],
        ['employee_number' => 'DEV-0015', 'department_code' => 'EDU', 'start_date' => '2022-06-06'],
    ];

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentEmployeeSeeder skipped: development seeding is disabled.');

            return;
        }

        if (Employee::query()->count() > 0) {
            $this->command?->warn('DevelopmentEmployeeSeeder skipped: development employees already exist.');

            return;
        }

        DB::transaction(function () {
            $departments = Department::query()->pluck('id', 'code')->all();
            $positions = Position::query()->pluck('id', 'code')->all();
            $role = Role::query()->where('name', RoleEnum::Employee->value)->firstOrFail();

            $accountEmails = $this->ensureEmployeeAccounts($role);
            $usersByEmail = User::query()->pluck('id', 'email')->all();

            foreach (self::EMPLOYEES as $emp) {
                Employee::updateOrCreate(
                    ['employee_number' => $emp['employee_number']],
                    [
                        'user_id' => $usersByEmail[$accountEmails[$emp['employee_number']]],
                        'first_name' => $emp['first_name'],
                        'last_name' => $emp['last_name'],
                        'position_id' => $positions[$emp['position_code']],
                        'department_id' => $departments[$emp['department_code']],
                        'employment_status' => $emp['employment_status'],
                        'employment_type' => $emp['employment_type'],
                        'date_hired' => $emp['date_hired'],
                        'date_ended' => $emp['employment_status'] === 'inactive' ? '2026-08-31' : null,
                        'gender' => $emp['gender'],
                        'date_of_birth' => $emp['date_of_birth'],
                        'phone' => $emp['phone'],
                        'address' => $emp['address'],
                    ],
                );
            }

            $this->assignSupervisors();
            $this->seedDepartmentHeads();
        });
    }

    /**
     * Every employee gets a login account. The four canonical role accounts
     * are reused; the rest receive a deterministic dev-XXXX account with the
     * Employee role and the development password set in config/jpc.php.
     *
     * Maps employee number -> email so the upserts can link user_id.
     *
     * @return array<string, string>
     */
    private function ensureEmployeeAccounts(Role $role): array
    {
        $accountEmails = [];
        $existingAccounts = [];

        foreach (self::EMPLOYEES as $emp) {
            if ($emp['user_email'] !== null) {
                $accountEmails[$emp['employee_number']] = $emp['user_email'];

                continue;
            }

            $email = strtolower(str_replace('DEV-', 'dev-', $emp['employee_number'])).'@jpc.local';
            $accountEmails[$emp['employee_number']] = $email;

            if (isset($existingAccounts[$email])) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $emp['first_name'].' '.$emp['last_name'],
                    'password' => config('jpc.dev_passwords.employee'),
                    'role_id' => $role->id,
                    'status' => UserStatus::Active,
                ],
            );
            $user->roles()->sync([$role->id]);
            $existingAccounts[$email] = true;
        }

        return $accountEmails;
    }

    private function assignSupervisors(): void
    {
        $employees = Employee::query()->pluck('id', 'employee_number')->all();

        foreach (self::SUPERVISORS as $employeeNumber => $supervisorNumber) {
            Employee::query()
                ->whereKey($employees[$employeeNumber])
                ->update(['supervisor_id' => $supervisorNumber !== null ? $employees[$supervisorNumber] : null]);
        }
    }

    private function seedDepartmentHeads(): void
    {
        foreach (self::DEPARTMENT_HEADS as $head) {
            $employee = Employee::query()->where('employee_number', $head['employee_number'])->firstOrFail();
            $department = Department::query()->where('code', $head['department_code'])->firstOrFail();

            DepartmentHead::updateOrCreate(
                ['department_id' => $department->id, 'employee_id' => $employee->id],
                [
                    'start_date' => $head['start_date'],
                    'end_date' => null,
                    'is_current' => true,
                ],
            );
        }
    }
}
