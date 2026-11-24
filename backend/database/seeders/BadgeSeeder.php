<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Seeds the JPC Portal achievement badges.
 *
 * Idempotent: updateOrCreate() keyed by the unique badge code, so re-running
 * never duplicates rows and never wipes awards. Includes all official JPC Portal
 * achievement badges with their distinct categories, tiers, icons, and criteria.
 */
class BadgeSeeder extends Seeder
{
    /**
     * All seeded badges with descriptions, categories, tiers, and criteria.
     *
     * @var array<int, array{code: string, name: string, icon: string, category: string, tier: string, description: string, criteria_type: string, criteria_value: array<string, mixed>, is_active: bool, allow_repeats: bool}>
     */
    private const BADGES = [
        [
            'code' => 'EMPLOYEE_OF_MONTH',
            'name' => 'Employee of the Month',
            'icon' => 'trophy',
            'category' => 'Performance',
            'tier' => 'Gold',
            'description' => 'Ranked first among all employees for the month.',
            'criteria_type' => 'monthly_rank',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly', 'description' => 'Highest overall performance score for the month.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'ON_TIME_HERO',
            'name' => 'On-Time Hero',
            'icon' => 'clock',
            'category' => 'Punctuality',
            'tier' => 'Silver',
            'description' => 'Submitted all required reports and tasks on time.',
            'criteria_type' => 'on_time_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly', 'description' => '100% on-time submission rate.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'OUTSTANDING',
            'name' => 'Outstanding Employee',
            'icon' => 'star',
            'category' => 'Performance',
            'tier' => 'Gold',
            'description' => 'Recognized for excellent overall performance.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 90, 'period' => 'monthly', 'description' => 'Performance score of at least 90.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'PERFECT_SUBMISSION',
            'name' => 'Perfect Submission',
            'icon' => 'check-circle',
            'category' => 'Punctuality',
            'tier' => 'Platinum',
            'description' => 'Completed all assigned tasks with full completion and on-time rates.',
            'criteria_type' => 'combined_rate',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly', 'description' => '100% completion and on-time submission.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'REPORT_EXCELLENCE',
            'name' => 'Report Excellence',
            'icon' => 'file-text',
            'category' => 'Performance',
            'tier' => 'Silver',
            'description' => 'Demonstrated excellence in submitting quality reports.',
            'criteria_type' => 'approved_reports',
            'criteria_value' => ['threshold' => 6, 'period' => 'monthly', 'description' => 'At least 6 approved reports during the month.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'TOP_PERFORMER',
            'name' => 'Top Performer',
            'icon' => 'award',
            'category' => 'Performance',
            'tier' => 'Platinum',
            'description' => 'Achieved an outstanding performance rating.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 95, 'period' => 'monthly', 'description' => 'Performance score of at least 95.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'CONSISTENCY',
            'name' => 'Consistency Award',
            'icon' => 'repeat',
            'category' => 'Progress',
            'tier' => 'Gold',
            'description' => 'Maintained consistently good performance.',
            'criteria_type' => 'consecutive_months',
            'criteria_value' => ['threshold' => 3, 'min_score' => 85, 'period' => 'monthly', 'description' => 'Performance score of at least 85 for 3 consecutive months.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'CONSISTENT_PROGRESS',
            'name' => 'Consistent Progress',
            'icon' => 'trending-up',
            'category' => 'Progress',
            'tier' => 'Silver',
            'description' => 'Maintained continuous improvement and consistent performance.',
            'criteria_type' => 'overall_score',
            'criteria_value' => ['threshold' => 85, 'period' => 'monthly', 'description' => 'Performance score of at least 85 for the applicable period.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'GOAL_ACHIEVER',
            'name' => 'Goal Achiever',
            'icon' => 'target',
            'category' => 'Goals',
            'tier' => 'Gold',
            'description' => 'Successfully accomplished all assigned KPI targets.',
            'criteria_type' => 'targets_completed',
            'criteria_value' => ['threshold' => 100, 'period' => 'monthly', 'description' => '100% completion of assigned KPI targets.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'RISING_STAR',
            'name' => 'Rising Star',
            'icon' => 'trending-up',
            'category' => 'Progress',
            'tier' => 'Bronze',
            'description' => 'Showed significant improvement in performance.',
            'criteria_type' => 'score_improvement',
            'criteria_value' => ['threshold' => 10, 'period' => 'monthly', 'description' => 'Improved performance score by at least 10 points compared to the previous month.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'TEAM_PLAYER',
            'name' => 'Team Player',
            'icon' => 'user-check',
            'category' => 'Collaboration',
            'tier' => 'Silver',
            'description' => 'Actively participates in team activities and collaborative tasks.',
            'criteria_type' => 'team_collaboration',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly', 'description' => 'Recognized for teamwork and collaboration.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'INNOVATION_AWARD',
            'name' => 'Innovation Award',
            'icon' => 'zap',
            'category' => 'Innovation',
            'tier' => 'Gold',
            'description' => 'Introduced an approved improvement or innovative idea.',
            'criteria_type' => 'innovation',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly', 'description' => 'An approved innovation or improvement idea.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'MILESTONE_ACHIEVER',
            'name' => 'Milestone Achiever',
            'icon' => 'check-square',
            'category' => 'Milestones',
            'tier' => 'Silver',
            'description' => 'Successfully completed a significant assigned project or milestone.',
            'criteria_type' => 'milestone_completed',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly', 'description' => 'Completed an assigned milestone or project.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'DEDICATED_EMPLOYEE',
            'name' => 'Dedicated Employee',
            'icon' => 'shield-check',
            'category' => 'Dedication',
            'tier' => 'Platinum',
            'description' => 'Maintained consistent task completion and progress.',
            'criteria_type' => 'consecutive_months',
            'criteria_value' => ['threshold' => 6, 'min_score' => 85, 'period' => 'monthly', 'description' => 'Consistent performance for 6 consecutive months.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'QUICK_FINISHER',
            'name' => 'Quick Finisher',
            'icon' => 'clock',
            'category' => 'Punctuality',
            'tier' => 'Bronze',
            'description' => 'Completed assigned tasks ahead of their deadlines.',
            'criteria_type' => 'ahead_of_deadline',
            'criteria_value' => ['threshold' => 1, 'period' => 'monthly', 'description' => 'Successfully completed tasks before their due dates.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'QUALITY_EXCELLENCE',
            'name' => 'Quality Excellence',
            'icon' => 'badge-check',
            'category' => 'Quality',
            'tier' => 'Gold',
            'description' => 'Consistently received high-quality ratings on submitted reports or work.',
            'criteria_type' => 'quality_rating',
            'criteria_value' => ['threshold' => 90, 'period' => 'monthly', 'description' => 'Consistently high quality ratings.'],
            'is_active' => true,
            'allow_repeats' => true,
        ],
        [
            'code' => 'TASK_CHAMPION',
            'name' => 'Task Champion',
            'icon' => 'check-square',
            'category' => 'Performance',
            'tier' => 'Bronze',
            'description' => 'Completed at least 10 assigned tasks during the month.',
            'criteria_type' => 'completed_tasks',
            'criteria_value' => ['threshold' => 10, 'period' => 'monthly', 'description' => 'Complete at least 10 tasks in a month.'],
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
                    'tier' => $badge['tier'],
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
