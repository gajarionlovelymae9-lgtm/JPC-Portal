<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development monthly performance records (June-August 2026).
 *
 * Three profiles are represented: high-performers, average performers and
 * at-risk performers. Values are DEVELOPMENT SAMPLE DATA only.
 *
 * Gated by config("jpc.seed_development.enabled") and skips when records
 * already exist.
 */
class DevelopmentPerformanceSeeder extends Seeder
{
    /**
     * Base profile per employee number.
     *
     * @var array<string, array{
     *     completion: int,
     *     on_time: int,
     *     approval: int,
     *     quality: int,
     *     target: int,
     *     status: string
     * }>
     */
    private const PROFILES = [
        'DEV-0001' => ['completion' => 62, 'on_time' => 55, 'approval' => 65, 'quality' => 66, 'target' => 60, 'status' => 'at_risk'],
        'DEV-0002' => ['completion' => 86, 'on_time' => 80, 'approval' => 88, 'quality' => 84, 'target' => 85, 'status' => 'consistent'],
        'DEV-0003' => ['completion' => 88, 'on_time' => 83, 'approval' => 90, 'quality' => 86, 'target' => 90, 'status' => 'consistent'],
        'DEV-0004' => ['completion' => 100, 'on_time' => 100, 'approval' => 97, 'quality' => 96, 'target' => 100, 'status' => 'improving'],
        'DEV-0005' => ['completion' => 82, 'on_time' => 78, 'approval' => 84, 'quality' => 82, 'target' => 85, 'status' => 'consistent'],
        'DEV-0006' => ['completion' => 88, 'on_time' => 85, 'approval' => 89, 'quality' => 87, 'target' => 90, 'status' => 'consistent'],
        'DEV-0007' => ['completion' => 80, 'on_time' => 75, 'approval' => 82, 'quality' => 81, 'target' => 80, 'status' => 'consistent'],
        'DEV-0008' => ['completion' => 84, 'on_time' => 79, 'approval' => 86, 'quality' => 83, 'target' => 85, 'status' => 'consistent'],
        'DEV-0009' => ['completion' => 60, 'on_time' => 52, 'approval' => 62, 'quality' => 64, 'target' => 60, 'status' => 'at_risk'],
        'DEV-0010' => ['completion' => 90, 'on_time' => 86, 'approval' => 92, 'quality' => 89, 'target' => 90, 'status' => 'consistent'],
        'DEV-0011' => ['completion' => 83, 'on_time' => 80, 'approval' => 85, 'quality' => 82, 'target' => 85, 'status' => 'consistent'],
        'DEV-0012' => ['completion' => 89, 'on_time' => 85, 'approval' => 91, 'quality' => 88, 'target' => 90, 'status' => 'consistent'],
        'DEV-0013' => ['completion' => 100, 'on_time' => 98, 'approval' => 98, 'quality' => 97, 'target' => 100, 'status' => 'improving'],
        'DEV-0014' => ['completion' => 79, 'on_time' => 74, 'approval' => 80, 'quality' => 78, 'target' => 80, 'status' => 'consistent'],
        'DEV-0015' => ['completion' => 98, 'on_time' => 96, 'approval' => 96, 'quality' => 95, 'target' => 100, 'status' => 'improving'],
    ];

    /**
     * [period_start, period_end, month offset]
     *
     * @var array<int, array{string, string, int}>
     */
    private const PERIODS = [
        ['2026-06-01', '2026-06-30', 0],
        ['2026-07-01', '2026-07-31', 1],
        ['2026-08-01', '2026-08-31', -1],
    ];

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentPerformanceSeeder skipped: development seeding is disabled.');

            return;
        }

        if (EmployeePerformanceRecord::query()->count() > 0) {
            $this->command?->warn('DevelopmentPerformanceSeeder skipped: development performance records already exist.');

            return;
        }

        $academicYear = AcademicYear::query()->firstOrFail();

        DB::transaction(function () use ($academicYear) {
            $employees = Employee::query()->pluck('id', 'employee_number')->all();

            foreach (self::PROFILES as $employeeNumber => $profile) {
                $employeeId = $employees[$employeeNumber];

                foreach (self::PERIODS as [$start, $end, $delta]) {
                    $completion = $this->clamp($profile['completion'] + $delta);
                    $onTime = $this->clamp($profile['on_time'] + $delta);
                    $approval = $this->clamp($profile['approval'] + ($delta > 0 ? 1 : -1));
                    $quality = $this->clamp($profile['quality'] + $delta);
                    $target = $this->clamp($profile['target'] + ($delta > 0 ? 1 : -1));
                    $overall = round(0.4 * $completion + 0.3 * $onTime + 0.3 * $quality, 2);

                    $record = EmployeePerformanceRecord::firstOrNew([
                        'employee_id' => $employeeId,
                        'academic_year_id' => $academicYear->id,
                        'period_type' => 'monthly',
                        'period_start' => $start,
                        'period_end' => $end,
                    ]);
                    $record->forceFill([
                        'completion_rate' => $completion,
                        'on_time_rate' => $onTime,
                        'approval_rate' => $approval,
                        'quality_score' => $quality,
                        'target_achievement_rate' => $target,
                        'overall_score' => $overall,
                        'performance_status' => $profile['status'],
                    ])->save();
                }
            }
        });
    }

    private function clamp(float $value, float $min = 45, float $max = 100): float
    {
        return max($min, min($max, $value));
    }
}
