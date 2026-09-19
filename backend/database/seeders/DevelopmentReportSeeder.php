<?php

namespace Database\Seeders;

use App\Models\AiAnalysis;
use App\Models\AiSummary;
use App\Models\AiValidationItem;
use App\Models\AiValidationResult;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportApproval;
use App\Models\ReportReview;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds development reports covering every workflow state:
 * approved, pending (submitted), late, rejected and revision (v1 -> v2).
 *
 * No report files are created because no physical development files exist
 * (see Phase 3.1-D Step 24 — never seed broken storage references).
 * AI records are labelled DEVELOPMENT SAMPLE DATA; no external provider is
 * ever called from a seeder.
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled").
 */
class DevelopmentReportSeeder extends Seeder
{
    private const AI_PROVIDER = 'jpc.sample';

    private const AI_MODEL = 'development-sample';

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reports(): array
    {
        return [
            [
                'task' => 'Submit Faculty Accomplishment Report',
                'employee' => 'DEV-0004',
                'title' => 'Faculty Accomplishment Report — July 2026',
                'status' => 'approved',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-07-10',
                'deadline' => '2026-07-15',
                'submitted_at' => '2026-07-10 09:30:00',
                'approved_at' => '2026-07-18 10:00:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => 'employee@jpc.local', 'notes' => 'Initial submission.', 'submitted_at' => '2026-07-10 09:30:00'],
                ],
                'scores' => [0.95, 0.90, 0.88, 0.94, 0.85, 0.92, 0.91],
                'result' => 'pass',
                'summary' => true,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Accomplishments are well documented and aligned with the plan.', 'reviewed_at' => '2026-07-17 14:00:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-07-18 10:00:00'],
                ],
                'notifications' => [
                    ['user_email' => 'employee@jpc.local', 'type' => 'report_approved', 'title' => 'Report approved', 'message' => 'Your Faculty Accomplishment Report — July 2026 was approved.'],
                ],
            ],
            [
                'task' => 'Submit Student Progress Report',
                'employee' => 'DEV-0006',
                'title' => 'Student Progress Report — Quarter 1',
                'status' => 'approved',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-09-05',
                'deadline' => '2026-09-10',
                'submitted_at' => '2026-09-05 08:45:00',
                'approved_at' => '2026-09-12 09:15:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-09-05 08:45:00'],
                ],
                'scores' => [0.96, 0.93, 0.91, 0.95, 0.90, 0.93, 0.93],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Clear progress narrative.', 'reviewed_at' => '2026-09-11 11:00:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-09-12 09:15:00'],
                ],
                'notifications' => [],
            ],
            [
                'task' => 'Submit Research Progress Report',
                'employee' => 'DEV-0009',
                'title' => 'Research Progress Report — Current Cycle',
                'status' => 'submitted',
                'version' => 1,
                'is_late' => true,
                'submission_date' => '2026-08-14',
                'deadline' => '2026-08-10',
                'submitted_at' => '2026-08-14 17:20:00',
                'approved_at' => null,
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Submitted after deadline.', 'submitted_at' => '2026-08-14 17:20:00'],
                ],
                'scores' => [0.90, 0.87, 0.82, 0.89, 0.80, 0.88, 0.88],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [],
                'approvals' => [],
                'notifications' => [
                    ['user_email' => 'departmenthead@jpc.local', 'type' => 'report_submitted', 'title' => 'Report submitted late', 'message' => 'A research progress report was submitted after its deadline and is awaiting review.'],
                ],
            ],
            [
                'task' => 'Submit Business Faculty Survey Report',
                'employee' => 'DEV-0007',
                'title' => 'Business Faculty Survey Report',
                'status' => 'rejected',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-08-12',
                'deadline' => '2026-08-15',
                'submitted_at' => '2026-08-12 10:00:00',
                'approved_at' => null,
                'rejected_at' => '2026-08-18 15:30:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-08-12 10:00:00'],
                ],
                'scores' => [0.88, 0.85, 0.79, 0.80, 0.78, 0.86, 0.84],
                'result' => 'warning',
                'summary' => false,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'departmenthead@jpc.local', 'decision' => 'rejected', 'comments' => 'Insufficient survey response details and missing breakdown tables. Please revise and resubmit.', 'reviewed_at' => '2026-08-18 15:30:00'],
                ],
                'approvals' => [],
                'notifications' => [
                    ['user_email' => 'admin@jpc.local', 'type' => 'report_rejected', 'title' => 'Report rejected', 'message' => 'Business Faculty Survey Report was rejected and sent back for revision.'],
                ],
            ],
            [
                'task' => 'Submit Monthly Attendance Report',
                'employee' => 'DEV-0013',
                'title' => 'Monthly Attendance Report — June 2026',
                'status' => 'approved',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-07-02',
                'deadline' => '2026-07-05',
                'submitted_at' => '2026-07-02 09:00:00',
                'approved_at' => '2026-07-06 08:30:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-07-02 09:00:00'],
                ],
                'scores' => [0.98, 0.97, 0.95, 0.97, 0.96, 0.98, 0.97],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Attendance records complete.', 'reviewed_at' => '2026-07-05 16:00:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-07-06 08:30:00'],
                ],
                'notifications' => [],
            ],
            [
                'task' => 'Submit Curriculum Development Report',
                'employee' => 'DEV-0006',
                'title' => 'Curriculum Development Report',
                'status' => 'submitted',
                'version' => 2,
                'is_late' => false,
                'submission_date' => '2026-07-16',
                'deadline' => '2026-07-20',
                'submitted_at' => '2026-07-16 11:00:00',
                'approved_at' => null,
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-07-08 13:00:00'],
                    ['version_number' => 2, 'submitter_email' => null, 'notes' => 'Revised based on reviewer feedback.', 'submitted_at' => '2026-07-16 11:00:00'],
                ],
                'scores' => [0.93, 0.91, 0.89, 0.93, 0.88, 0.91, 0.91],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'revision_required', 'comments' => 'Version 1 rejected: include the department consultation minutes and projected timeline.', 'reviewed_at' => '2026-07-14 12:00:00'],
                ],
                'approvals' => [],
                'notifications' => [],
            ],
            [
                'task' => 'Submit Mid-Year Performance Review',
                'employee' => 'DEV-0004',
                'title' => 'Mid-Year Performance Review — 2026',
                'status' => 'approved',
                'version' => 2,
                'is_late' => false,
                'submission_date' => '2026-08-22',
                'deadline' => '2026-08-25',
                'submitted_at' => '2026-08-22 09:20:00',
                'approved_at' => '2026-09-02 10:30:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => 'employee@jpc.local', 'notes' => 'Initial submission.', 'submitted_at' => '2026-08-10 10:00:00'],
                    ['version_number' => 2, 'submitter_email' => 'employee@jpc.local', 'notes' => 'Revised after reviewer feedback.', 'submitted_at' => '2026-08-22 09:20:00'],
                ],
                'scores' => [0.95, 0.92, 0.90, 0.94, 0.89, 0.93, 0.92],
                'result' => 'pass',
                'summary' => true,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'revision_required', 'comments' => 'Version 1 rejected: add concrete metrics for each accomplishment.', 'reviewed_at' => '2026-08-18 14:00:00'],
                    ['review_type' => 'revision', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Version 2 approved with metrics included.', 'reviewed_at' => '2026-09-02 10:30:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-09-02 10:30:00'],
                ],
                'notifications' => [
                    ['user_email' => 'employee@jpc.local', 'type' => 'report_approved', 'title' => 'Report approved', 'message' => 'Your Mid-Year Performance Review — 2026 was approved.'],
                ],
            ],
            [
                'task' => 'Submit College Accomplishment Report',
                'employee' => 'DEV-0012',
                'title' => 'College Accomplishment Report — Period 1',
                'status' => 'approved',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-08-05',
                'deadline' => '2026-08-20',
                'submitted_at' => '2026-08-05 10:10:00',
                'approved_at' => '2026-08-10 09:00:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-08-05 10:10:00'],
                ],
                'scores' => [0.94, 0.92, 0.90, 0.93, 0.88, 0.92, 0.91],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Comprehensive accomplishment report.', 'reviewed_at' => '2026-08-09 15:00:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'admin@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-08-10 09:00:00'],
                ],
                'notifications' => [],
            ],
            [
                'task' => 'Deploy IT Analytics Dashboard Prototype',
                'employee' => 'DEV-0010',
                'title' => 'IT Analytics Dashboard Prototype Report',
                'status' => 'approved',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-09-01',
                'deadline' => '2026-09-30',
                'submitted_at' => '2026-09-01 14:00:00',
                'approved_at' => '2026-09-08 11:30:00',
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => null, 'notes' => 'Initial submission.', 'submitted_at' => '2026-09-01 14:00:00'],
                ],
                'scores' => [0.96, 0.94, 0.92, 0.95, 0.91, 0.94, 0.93],
                'result' => 'pass',
                'summary' => true,
                'reviews' => [
                    ['review_type' => 'initial', 'reviewer_email' => 'departmenthead@jpc.local', 'decision' => 'approved', 'comments' => 'Prototype meets the department requirements.', 'reviewed_at' => '2026-09-08 11:30:00'],
                ],
                'approvals' => [
                    ['approver_email' => 'departmenthead@jpc.local', 'decision' => 'approved', 'comments' => 'Approved.', 'approved_at' => '2026-09-08 11:30:00'],
                ],
                'notifications' => [
                    ['user_email' => 'departmenthead@jpc.local', 'type' => 'report_approved', 'title' => 'Report approved', 'message' => 'IT Analytics Dashboard Prototype Report was approved.'],
                ],
            ],
            [
                'task' => 'Prepare Monthly Grade Encoding Summary',
                'employee' => 'DEV-0004',
                'title' => 'Monthly Grade Encoding Summary — Report',
                'status' => 'submitted',
                'version' => 1,
                'is_late' => false,
                'submission_date' => '2026-09-10',
                'deadline' => '2026-09-25',
                'submitted_at' => '2026-09-10 16:00:00',
                'approved_at' => null,
                'versions' => [
                    ['version_number' => 1, 'submitter_email' => 'employee@jpc.local', 'notes' => 'Initial submission, awaiting review.', 'submitted_at' => '2026-09-10 16:00:00'],
                ],
                'scores' => [0.97, 0.95, 0.93, 0.96, 0.94, 0.96, 0.95],
                'result' => 'pass',
                'summary' => false,
                'reviews' => [],
                'approvals' => [],
                'notifications' => [
                    ['user_email' => 'employee@jpc.local', 'type' => 'report_submitted', 'title' => 'Report submitted', 'message' => 'Your Monthly Grade Encoding Summary — Report was submitted and is awaiting review.'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentReportSeeder skipped: development seeding is disabled.');

            return;
        }

        if (Report::query()->count() > 0) {
            $this->command?->warn('DevelopmentReportSeeder skipped: development reports already exist.');

            return;
        }

        DB::transaction(function () {
            $admin = User::query()->where('email', 'admin@jpc.local')->firstOrFail();
            $users = User::query()->pluck('id', 'email')->all();
            $tasks = Task::query()->pluck('id', 'title')->all();

            foreach ($this->reports() as $data) {
                $employee = Employee::query()->where('employee_number', $data['employee'])->firstOrFail();

                $report = new Report;
                $report->forceFill([
                    'task_id' => $tasks[$data['task']],
                    'employee_id' => $employee->id,
                    'title' => $data['title'],
                    'description' => 'DEVELOPMENT SAMPLE DATA — '.$data['title'],
                    'submission_date' => $data['submission_date'],
                    'deadline' => $data['deadline'],
                    'status' => $data['status'],
                    'is_late' => $data['is_late'],
                    'version' => $data['version'],
                    'submitted_at' => $data['submitted_at'],
                    'approved_at' => $data['approved_at'],
                    'rejected_at' => isset($data['rejected_at']) ? $data['rejected_at'] : null,
                ])->save();

                foreach ($data['versions'] as $version) {
                    ReportVersion::create([
                        'report_id' => $report->id,
                        'version_number' => $version['version_number'],
                        'submitted_by' => $version['submitter_email'] ? $users[$version['submitter_email']] : null,
                        'file_path' => null,
                        'notes' => $version['notes'],
                        'submitted_at' => $version['submitted_at'],
                    ]);
                }

                $alignment = $this->analysis($report, 'task_alignment');
                $this->validation($alignment, $data['scores'], $data['result']);

                if (! empty($data['summary'])) {
                    $summarization = $this->analysis($report, 'summarization');
                    $this->summary($summarization);
                }

                foreach ($data['reviews'] as $review) {
                    $reviewRecord = new ReportReview;
                    $reviewRecord->forceFill([
                        'report_id' => $report->id,
                        'reviewer_id' => $users[$review['reviewer_email']],
                        'review_type' => $review['review_type'],
                        'decision' => $review['decision'],
                        'comments' => $review['comments'],
                        'reviewed_at' => $review['reviewed_at'],
                    ])->save();
                }

                foreach ($data['approvals'] as $approval) {
                    $approvalRecord = new ReportApproval;
                    $approvalRecord->forceFill([
                        'report_id' => $report->id,
                        'approver_id' => $users[$approval['approver_email']],
                        'decision' => $approval['decision'],
                        'comments' => $approval['comments'],
                        'approved_at' => $approval['approved_at'],
                    ])->save();
                }

                foreach ($data['notifications'] as $notification) {
                    $this->notify($users[$notification['user_email']], $notification['type'], $notification['title'], $notification['message']);
                }
            }

            $this->notify(
                $admin->id,
                'system',
                'Development dataset loaded',
                'The Phase 3.1-D development sample data has been seeded successfully.',
            );
        });
    }

    private function analysis(Report $report, string $type): AiAnalysis
    {
        $started = now()->subDays(3)->addRealHour(-2);

        $analysis = new AiAnalysis;
        $analysis->forceFill([
            'report_id' => $report->id,
            'analysis_type' => $type,
            'provider' => self::AI_PROVIDER,
            'model' => self::AI_MODEL,
            'status' => 'completed',
            'input_hash' => null,
            'started_at' => $started,
            'completed_at' => $started->addRealHour(1),
        ])->save();

        return $analysis;
    }

    /**
     * @param  list<float>  $scores  [task_alignment, reference_alignment, completeness, relevance, evidence, clarity, overall]
     */
    private function validation(AiAnalysis $analysis, array $scores, string $result): AiValidationResult
    {
        [$task, $reference, $completeness, $relevance, $evidence, $clarity, $overall] = $scores;

        $validation = new AiValidationResult;
        $validation->forceFill([
            'report_id' => $analysis->report_id,
            'ai_analysis_id' => $analysis->id,
            'task_alignment_score' => $task,
            'reference_alignment_score' => $reference,
            'completeness_score' => $completeness,
            'relevance_score' => $relevance,
            'evidence_score' => $evidence,
            'clarity_score' => $clarity,
            'overall_score' => $overall,
            'result' => $result,
            'explanation' => 'DEVELOPMENT SAMPLE DATA — generated by DevelopmentReportSeeder; the AI engine will produce real values in a later phase.',
        ])->save();

        if ($result === 'warning') {
            $item = new AiValidationItem;
            $item->forceFill([
                'ai_validation_result_id' => $validation->id,
                'category' => 'completeness',
                'severity' => 'warning',
                'finding' => 'Some required supporting evidence appears to be missing.',
                'recommendation' => 'Attach the referenced supporting documents before final review.',
            ])->save();

            $item = new AiValidationItem;
            $item->forceFill([
                'ai_validation_result_id' => $validation->id,
                'category' => 'relevance',
                'severity' => 'warning',
                'finding' => 'Parts of the report drift from the assigned task scope.',
                'recommendation' => 'Keep the narrative focused on the task deliverables.',
            ])->save();
        }

        return $validation;
    }

    private function summary(AiAnalysis $analysis): AiSummary
    {
        $summary = new AiSummary;
        $summary->forceFill([
            'report_id' => $analysis->report_id,
            'ai_analysis_id' => $analysis->id,
            'summary' => 'DEVELOPMENT SAMPLE SUMMARY: the report describes the period results, key accomplishments and next steps. This text is sample data only and will be generated by the AI summarization engine in a later phase.',
            'key_accomplishments' => ['Submitted the required deliverables on time', 'Documented measurable outcomes'],
            'key_findings' => ['All major deliverables were completed'],
            'issues' => ['Minor formatting inconsistencies noted'],
            'recommendations' => ['Use the standard report template for future submissions'],
            'action_items' => ['Apply template improvements next cycle'],
            'generated_at' => now()->subDays(3)->addRealHour(-1),
        ])->save();

        return $summary;
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
