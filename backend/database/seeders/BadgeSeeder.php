<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Seeds the JPC Portal achievement badges.
 *
 * Idempotent: updateOrCreate() keyed by the unique badge code, so re-running
 * never duplicates rows and never wipes awards. The four core badges
 * suggested by the Achievement & Badges module (Top Performer, On-Time Hero,
 * Goal Achiever, Consistent Progress) are always present; the rest of the
 * catalog keeps its historical definitions with an explicit category added.
 */
class BadgeSeeder extends Seeder
{
    /**
     * All seeded badges are monthly-recurring by design, so allow_repeats is
     * true: the same employee may earn the badge again in a later period.
     * Admin-created badges default to one award per academic year.
     *
     * @var array<int, array{code: string, name: string, icon: string, category: string, description: string, criteria_type: string, criteria_value: array<string, mixed>, is_active: bool, allow_repeats: bool}>
     */
    private const BADGES = [
        [
            'code' => 'TOP_PERFORMER',
            'name' => 'Top Performer',
            'icon' => 'award',
            'category' => 'Performance',
            'description' => 'Achieved an overall performance score of at least 95 for the month.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 95, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'ON_TIME_HERO',
            'name' => 'On-Time Hero',
            'icon' => 'clock',
            'category' => 'Punctuality',
            'description' => 'Submitted every report on time (100% on-time rate) for the month.',
            'criteria_type' => 'on_time_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'GOAL_ACHIEVER',
            'name' => 'Goal Achiever',
            'icon' => 'target',
            'category' => 'Goals',
            'description' => 'Completed every assigned KPI target for the month (100% target completion).',
            'criteria_type' => 'targets_completed',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'CONSISTENT_PROGRESS',
            'name' => 'Consistent Progress',
            'icon' => 'trending-up',
            'category' => 'Progress',
            'description' => 'Maintained an overall score of at least 85 for at least three consecutive months.',
            'criteria_type' => 'consecutive_months',
            'criteria_value' => ['threshold' => 3, 'min_score' => 85, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'PERFECT_SUBMISSION',
            'name' => 'Perfect Submission',
            'icon' => 'check-circle',
            'category' => 'Punctuality',
            'description' => 'Submitted every assignment with full completion and on-time rates.',
            'criteria_type' => 'on_time_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'TASK_CHAMPION',
            'name' => 'Task Champion',
            'icon' => 'check-square',
            'category' => 'Performance',
            'description' => 'Completed at least 10 assigned tasks during the month.',
            'criteria_type' => 'completed_tasks',
            'criteria_value' => ['threshold' => 10, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'REPORT_EXCELLENCE',
            'name' => 'Report Excellence',
            'icon' => 'file-text',
            'category' => 'Performance',
            'description' => 'Had at least 6 reports approved during the month.',
            'criteria_type' => 'approved_reports',
            'criteria_value' => ['threshold' => 6, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'CONSISTENCY',
            'name' => 'Consistency Award',
            'icon' => 'repeat',
            'category' => 'Progress',
            'description' => 'Maintained consistently good performance (score of at least 85).',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 85, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'OUTSTANDING',
            'name' => 'Outstanding Employee',
            'icon' => 'star',
            'category' => 'Performance',
            'description' => 'Recognized for an overall performance score of at least 90.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 90, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'EMPLOYEE_OF_MONTH',
            'name' => 'Employee of the Month',
            'icon' => 'user-check',
            'category' => 'Performance',
            'description' => 'Ranked first among all employees for the month.',
            'criteria_type' => 'monthly_rank',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => true,
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
                    'category' => $badge['category'],
                    'description' => $badge['description'],
                    'criteria_type' => $badge['criteria_type'],
                    'criteria_value' => $badge['criteria_value'],
                    'is_active' => $badge['is_active'],
                    'allow_repeats' => $badge['allow_repeats'],
                ],
            );
        }
    }
}
