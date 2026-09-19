<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Seeds the JPC Portal achievement badges.
 *
 * Badge calculation is implemented in a later business-logic phase; this
 * seeder only defines the badge catalog. Idempotent: updateOrCreate() keyed
 * by the unique badge code.
 */
class BadgeSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, icon: string, description: string, criteria_type: string, criteria_value: array<string, mixed>, is_active: bool}>
     */
    private const BADGES = [
        [
            'code' => 'TOP_PERFORMER',
            'name' => 'Top Performer',
            'icon' => 'award',
            'description' => 'Achieved the highest overall performance in a period.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 95, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'PERFECT_SUBMISSION',
            'name' => 'Perfect Submission',
            'icon' => 'check-circle',
            'description' => 'Submitted every assignment with full marks.',
            'criteria_type' => 'on_time_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'ON_TIME_HERO',
            'name' => 'On-Time Hero',
            'icon' => 'clock',
            'description' => 'Never missed a deadline for an entire period.',
            'criteria_type' => 'on_time_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'TASK_CHAMPION',
            'name' => 'Task Champion',
            'icon' => 'target',
            'description' => 'Completed a high number of assigned tasks.',
            'criteria_type' => 'completed_tasks',
            'criteria_value' => ['threshold' => 10, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'REPORT_EXCELLENCE',
            'name' => 'Report Excellence',
            'icon' => 'file-text',
            'description' => 'Had many reports approved during the period.',
            'criteria_type' => 'approved_reports',
            'criteria_value' => ['threshold' => 6, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'CONSISTENCY',
            'name' => 'Consistency Award',
            'icon' => 'trending-up',
            'description' => 'Maintained consistently good performance.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 85, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'OUTSTANDING',
            'name' => 'Outstanding Employee',
            'icon' => 'star',
            'description' => 'Recognized for outstanding contributions.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 90, 'period' => 'monthly'],
            'is_active' => true,
        ],
        [
            'code' => 'EMPLOYEE_OF_MONTH',
            'name' => 'Employee of the Month',
            'icon' => 'user-check',
            'description' => 'Ranked first among all employees for the month.',
            'criteria_type' => 'monthly_rank',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly'],
            'is_active' => true,
        ],
    ];

    public function run(): void
    {
        foreach (self::BADGES as $badge) {
            Badge::updateOrCreate(
                ['code' => $badge['code']],
                [
                    'name' => $badge['name'],
                    'icon' => $badge['icon'],
                    'description' => $badge['description'],
                    'criteria_type' => $badge['criteria_type'],
                    'criteria_value' => $badge['criteria_value'],
                    'is_active' => $badge['is_active'],
                ],
            );
        }
    }
}
