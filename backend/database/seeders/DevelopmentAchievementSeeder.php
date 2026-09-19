<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\MonthlyAchievement;
use App\Models\Notification;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development monthly achievements (top-3 every month) and a set of
 * sample badge awards consistent with the seeded performance records.
 *
 * DEVELEOPMENT SAMPLE DATA only. Gated by config("jpc.seed_development.enabled")
 * and skips when achievements already exist.
 */
class DevelopmentAchievementSeeder extends Seeder
{
    private const PERIOD_START = '2026-06-01';

    private const PERIOD_END = '2027-05-31';

    /**
     * Deterministic badge awards: employee number => badge codes.
     *
     * @var array<string, list<string>>
     */
    private const AWARDS = [
        'DEV-0004' => ['TOP_PERFORMER', 'PERFECT_SUBMISSION', 'ON_TIME_HERO', 'REPORT_EXCELLENCE', 'OUTSTANDING', 'EMPLOYEE_OF_MONTH'],
        'DEV-0013' => ['TASK_CHAMPION', 'EMPLOYEE_OF_MONTH', 'CONSISTENCY'],
        'DEV-0015' => ['OUTSTANDING', 'CONSISTENCY'],
        'DEV-0010' => ['TASK_CHAMPION'],
        'DEV-0012' => ['REPORT_EXCELLENCE', 'CONSISTENCY'],
        'DEV-0011' => ['CONSISTENCY'],
        'DEV-0008' => ['TASK_CHAMPION'],
    ];

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentAchievementSeeder skipped: development seeding is disabled.');

            return;
        }

        if (MonthlyAchievement::query()->count() > 0 || EmployeeBadge::query()->count() > 0) {
            $this->command?->warn('DevelopmentAchievementSeeder skipped: development achievements already exist.');

            return;
        }

        DB::transaction(function () {
            $academicYear = AcademicYear::query()->firstOrFail('id');
            $admin = User::query()->where('email', 'admin@jpc.local')->firstOrFail();
            $employeeOfMonthUser = User::query()->where('email', 'employee@jpc.local')->first();

            $employees = Employee::query()->pluck('id', 'employee_number')->all();
            $badges = Badge::query()->pluck('id', 'code')->all();

            $this->seedMonthlyAchievements($academicYear->id);
            $this->seedBadges($academicYear->id, $admin->id, $employees, $badges, $employeeOfMonthUser?->id);
        });
    }

    private function seedMonthlyAchievements(int $academicYearId): void
    {
        foreach ([6, 7, 8] as $month) {
            $year = 2026;
            $periodStart = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-01';
            $periodEnd = date('Y-m-t', strtotime($periodStart));

            $records = EmployeePerformanceRecord::query()
                ->where('academic_year_id', $academicYearId)
                ->where('period_start', $periodStart)
                ->orderByDesc('overall_score')
                ->get();

            foreach ($records->take(3)->values() as $index => $record) {
                $rank = $index + 1;

                $achievement = MonthlyAchievement::firstOrNew([
                    'employee_id' => $record->employee_id,
                    'academic_year_id' => $academicYearId,
                    'month' => $month,
                    'year' => $year,
                ]);
                $achievement->forceFill([
                    'completion_rate' => $record->completion_rate,
                    'on_time_rate' => $record->on_time_rate,
                    'approved_reports' => Report::query()
                        ->where('employee_id', $record->employee_id)
                        ->where('status', 'approved')
                        ->whereBetween('approved_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
                        ->count(),
                    'completed_tasks' => TaskAssignment::query()
                        ->where('employee_id', $record->employee_id)
                        ->where('status', 'completed')
                        ->whereBetween('completed_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
                        ->count(),
                    'overall_score' => $record->overall_score,
                    'rank' => $rank,
                    'is_employee_of_month' => $rank === 1,
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, int>  $employees
     * @param  array<string, int>  $badges
     */
    private function seedBadges(int $academicYearId, int $adminId, array $employees, array $badges, ?int $employeeOfMonthUserId): void
    {
        $awardedAt = [
            '2026-06-30 17:00:00',
            '2026-07-31 17:00:00',
            '2026-08-31 17:00:00',
        ];

        foreach (self::AWARDS as $employeeNumber => $codes) {
            $employeeId = $employees[$employeeNumber];
            $monthIndex = (int) substr($employeeNumber, -2) % count($awardedAt);
            $employeeOfMonthBadgeId = $badges['EMPLOYEE_OF_MONTH'];

            foreach ($codes as $code) {
                $badgeId = $badges[$code];
                $awardedBy = $code === 'EMPLOYEE_OF_MONTH' ? $adminId : $adminId;
                $periodStart = $code === 'EMPLOYEE_OF_MONTH' ? '2026-06-01' : self::PERIOD_START;

                $award = EmployeeBadge::firstOrNew([
                    'employee_id' => $employeeId,
                    'badge_id' => $badgeId,
                    'academic_year_id' => $academicYearId,
                    'period_start' => $periodStart,
                    'period_end' => self::PERIOD_END,
                ]);
                $award->forceFill([
                    'awarded_at' => $awardedAt[$monthIndex],
                    'awarded_by' => $awardedBy,
                    'criteria_snapshot' => [
                        'overall_score' => null,
                        'source' => 'development.seeder',
                        'note' => 'DEVELOPMENT SAMPLE DATA — determined by DevelopmentAchievementSeeder.',
                    ],
                ])->save();

                if ($code === 'EMPLOYEE_OF_MONTH' && $employeeOfMonthUserId !== null && $badgeId === $employeeOfMonthBadgeId) {
                    $this->notify($employeeOfMonthUserId, 'badge_awarded', 'You are Employee of the Month!', 'Congratulations on being recognized as Employee of the Month (2026).');
                }
            }
        }
    }

    private function notify(int $userId, string $type, string $title, string $message): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => ['source' => 'development.seeder'],
            'read_at' => null,
        ]);
    }
}
