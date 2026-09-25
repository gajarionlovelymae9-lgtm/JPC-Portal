<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calculation Version
    |--------------------------------------------------------------------------
    |
    | Version tag recorded on all generated performance records and snapshots.
    | Changing this preserves historical calculation auditing.
    |
    */
    'calculation_version' => 'v1.0',

    /*
    |--------------------------------------------------------------------------
    | Productivity Score Weights
    |--------------------------------------------------------------------------
    |
    | Centralized weights used for overall productivity score calculation.
    | Sum must equal 1.0 (100%).
    |
    */
    'weights' => [
        'task_completion' => 0.30,       // 30%
        'report_completion' => 0.20,     // 20%
        'on_time_submission' => 0.20,    // 20%
        'report_approval' => 0.15,       // 15%
        'target_achievement' => 0.15,    // 15%
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Rating Bands
    |--------------------------------------------------------------------------
    |
    | Categorical rating bands based on calculated productivity score (0 - 100).
    |
    */
    'rating_bands' => [
        [
            'key' => 'excellent',
            'label' => 'Excellent',
            'min_score' => 90.00,
            'max_score' => 100.00,
            'badge_color' => 'emerald',
        ],
        [
            'key' => 'very_good',
            'label' => 'Very Good',
            'min_score' => 80.00,
            'max_score' => 89.99,
            'badge_color' => 'blue',
        ],
        [
            'key' => 'good',
            'label' => 'Good',
            'min_score' => 70.00,
            'max_score' => 79.99,
            'badge_color' => 'amber',
        ],
        [
            'key' => 'needs_improvement',
            'label' => 'Needs Improvement',
            'min_score' => 60.00,
            'max_score' => 69.99,
            'badge_color' => 'orange',
        ],
        [
            'key' => 'unsatisfactory',
            'label' => 'Unsatisfactory',
            'min_score' => 0.00,
            'max_score' => 59.99,
            'badge_color' => 'rose',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Achievement Thresholds & Criteria
    |--------------------------------------------------------------------------
    |
    | Used by AchievementEvaluationService for awarding badges and recognizing
    | monthly milestones.
    |
    */
    'achievements' => [
        'top_performer_min_score' => 95.00,
        'on_time_hero_min_rate' => 100.00,
        'task_champion_min_completed' => 10,
        'report_excellence_min_approved' => 5,
        'consistency_min_score' => 85.00,
        'outstanding_min_score' => 90.00,
        'most_improved_min_delta' => 10.00, // percentage points improvement over prior month
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache lifetime in seconds for expensive ranking and performance queries.
    |
    */
    'cache_ttl' => 60,

];
