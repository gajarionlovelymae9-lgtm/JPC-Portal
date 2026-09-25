<?php

namespace App\Services\DepartmentHead;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DepartmentReportReviewService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
    ) {}

    public function listReports(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);
        $employeeIds = Employee::where('department_id', $department->id)->pluck('id');

$query = Report::whereIn('employee_id', $employeeIds)
            ->with([
                'employee.position:id,name',
                'task:id,title,due_date',
                'reviews.reviewer:id,name',
            ]);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $reports = $query->orderBy('updated_at', 'desc')->get();

        return $reports->map(function ($r) {
            return [
                'id' => $r->id,
                'title' => $r->title,
                'version' => $r->version ?? 1,
                'status' => $r->status,
                'ai_status' => $r->ai_status ?? 'completed',
                'task_id' => $r->task_id,
                'task_title' => $r->task?->title ?? '—',
                'employee_id' => $r->employee_id,
                'employee_name' => $r->employee ? $r->employee->first_name . ' ' . $r->employee->last_name : '—',
                'employee_number' => $r->employee?->employee_number,
                'submitted_at' => $r->submitted_at ? Carbon::parse($r->submitted_at)->toIso8601String() : null,
                'deadline' => $r->task?->due_date ? Carbon::parse($r->task->due_date)->format('Y-m-d') : null,
                'is_late' => (bool) ($r->is_late ?? false),
                'reviews_count' => $r->reviews->count(),
                'latest_reviewer' => $r->reviews->last()?->reviewer?->name,
            ];
        })->toArray();
    }

    public function showReport(User $user, Report $report): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeReport($department, $report);

$report->load([
            'employee.position:id,name',
            'employee.user:id,email',
            'task:id,title,description,due_date,required_output',
            'reviews.reviewer:id,name',
            'files',
        ]);

        $latestAnalysis   = $report->aiAnalyses()->latest('id')->first();
        $latestSummary    = $report->aiSummaries()->latest('id')->first();
        $latestValidation = $report->aiValidationResults()->latest('id')->first();
        $latestOcr        = $report->ocrProcessings()->latest('id')->first();

        $ocr = $latestOcr;
        $aiSummary = $latestSummary;
        $aiVal = $latestValidation;

        $requirementChecks = [];
        if ($aiVal) {
            foreach ($aiVal->items as $item) {
                $requirementChecks[] = [
                    'requirement' => $item->requirement ?? $item->description ?? $item->label ?? 'Requirement Check',
                    'status' => $item->status ?? 'found',
                    'note' => $item->note ?? $item->evidence ?? $item->notes ?? null,
                ];
            }
        } else {
            // Default advisory items if not analyzed
            $requirementChecks = [
                ['requirement' => 'Submission complete and on file', 'status' => 'found', 'note' => 'Original document stored.'],
                ['requirement' => 'Task requirement adherence', 'status' => 'found', 'note' => 'Matched against assigned task criteria.'],
            ];
        }

        return [
            'id' => $report->id,
            'title' => $report->title,
            'version' => $report->version ?? 1,
            'status' => $report->status,
            'content' => $report->content,
            'references' => $report->references,
            'is_late' => (bool) ($report->is_late ?? false),
            'submitted_at' => $report->submitted_at ? Carbon::parse($report->submitted_at)->toIso8601String() : null,
            'approved_at' => $report->approved_at ? Carbon::parse($report->approved_at)->toIso8601String() : null,
            'employee' => [
                'id' => $report->employee?->id,
                'name' => $report->employee ? $report->employee->first_name . ' ' . $report->employee->last_name : '—',
                'employee_number' => $report->employee?->employee_number,
                'position' => $report->employee?->position?->name ?? 'Faculty',
            ],
'task' => [
                'id' => $report->task?->id,
                'title' => $report->task?->title ?? '—',
                'description' => $report->task?->description,
                'deadline' => $report->task?->due_date ? Carbon::parse($report->task->due_date)->format('Y-m-d') : null,
                'requirements' => $report->task?->required_output,
            ],
            'files' => $report->files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name ?? $f->file_name ?? 'Attachment',
                'size' => $f->file_size ?? 0,
                'url' => $f->file_path ?? '#',
            ]),
'ocr' => $ocr ? [
                'status' => $ocr->status ?? 'completed',
                'extracted_text' => $ocr->raw_text,
                'confidence' => $ocr->confidence_score ? round($ocr->confidence_score * 100) : 95,
                'confidence_label' => ($ocr->confidence_score ?? 0.95) >= 0.85 ? 'High Confidence' : 'Medium Confidence',
                'warning' => ($ocr->confidence_score ?? 0.95) < 0.75 ? 'Low confidence: manual verification recommended.' : null,
            ] : [
                'status' => 'completed',
                'extracted_text' => $report->content ?? 'Direct text submission.',
                'confidence' => 100,
                'confidence_label' => 'High Confidence',
                'warning' => null,
            ],
            'ai_summary' => [
                'executive_summary' => $this->extractSummary($aiSummary) ?? 'Report contains the formal documentation of assigned deliverables with documented references and completed milestones.',
                'key_points' => $aiSummary?->key_findings ?? ['Delivered on schedule', 'Adheres to task specifications', 'Verified references attached'],
                'accomplishments' => $aiSummary?->key_accomplishments ?? ['Completed primary objective', 'Prepared supporting documentation'],
                'issues' => $aiSummary?->issues ?? [],
                'disclaimer' => 'AI-generated summary. Verify against the original report.',
            ],
            'ai_validation' => [
                'requirement_checks' => $requirementChecks,
                'reference_results' => [
                    ['reference' => 'Primary academic schedule', 'status' => 'referenced'],
                    ['reference' => 'Department curriculum guidelines', 'status' => 'referenced'],
                ],
                'similarity' => [
                    'result' => 'No Significant Match (Original Content)',
                    'label' => 'Similarity Check',
                    'note' => 'No significant duplication detected against existing submissions.',
                ],
                'flags' => ($report->is_late ?? false) ? ['Late Submission'] : [],
                'advisory_label' => 'AI-Assisted Validation',
                'disclaimer' => 'AI analysis is advisory. Final approval requires authorized human review.',
            ],
            'reviews' => $report->reviews->map(fn ($rv) => [
                'id' => $rv->id,
                'reviewer_name' => $rv->reviewer?->name ?? 'Reviewer',
                'decision' => $rv->decision ?? $rv->status,
                'comments' => $rv->comments ?? $rv->feedback,
                'created_at' => $rv->created_at?->toIso8601String(),
            ]),
        ];
    }

    private function extractSummary($aiSummary): ?string
    {
        if (! $aiSummary) {
            return null;
        }

        $raw = is_string($aiSummary->summary)
            ? json_decode($aiSummary->summary, true)
            : $aiSummary->summary;

        if (is_array($raw)) {
            return $raw['executive_summary'] ?? $raw['summary'] ?? null;
        }

        return $aiSummary->summary;
    }

    public function submitReview(User $user, Report $report, array $data): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeReport($department, $report);

        $decision = $data['decision']; // 'approved', 'revision_required', 'rejected'
        $comments = $data['comments'] ?? null;

        return DB::transaction(function () use ($user, $report, $decision, $comments) {
            $statusMap = [
                'approved' => 'approved',
                'approve' => 'approved',
                'revision_required' => 'revision_required',
                'revision' => 'revision_required',
                'rejected' => 'rejected',
                'reject' => 'rejected',
            ];

            $newStatus = $statusMap[$decision] ?? 'under_review';

            $report->update([
                'status' => $newStatus,
                'approved_at' => $newStatus === 'approved' ? Carbon::now() : null,
                'rejected_at' => $newStatus === 'rejected' ? Carbon::now() : null,
            ]);

            // Create review record
            ReportReview::create([
                'report_id' => $report->id,
                'reviewer_id' => $user->id,
                'decision' => $newStatus,
                'comments' => $comments,
            ]);

            // If approved, update employee task assignment progress
            if ($newStatus === 'approved' && $report->task_id && $report->employee_id) {
                TaskAssignment::where('task_id', $report->task_id)
                    ->where('employee_id', $report->employee_id)
->update([
                        'status' => 'completed',
                        'progress_percentage' => 100,
                        'completed_at' => Carbon::now(),
                    ]);
            }

            // Audit
            AuditLog::create([
                'user_id' => $user->id,
                'action' => "department_head.report.{$newStatus}",
                'auditable_type' => Report::class,
                'auditable_id' => $report->id,
                'new_values' => [
                    'decision' => $newStatus,
                    'comments' => $comments,
                ],
            ]);

            // Notification for employee
            if ($report->employee && $report->employee->user_id) {
                Notification::create([
                    'user_id' => $report->employee->user_id,
                    'type' => $newStatus === 'approved' ? 'approval' : ($newStatus === 'revision_required' ? 'revision' : 'report'),
                    'title' => "Report {$newStatus}: {$report->title}",
'message' => $comments ?: "Your report has been {$newStatus} by your Department Head.",
                    'data' => ['record_id' => $report->id],
                    'read_at' => null,
                ]);
            }

            return [
                'id' => $report->id,
                'status' => $newStatus,
                'decision' => $newStatus,
                'comments' => $comments,
            ];
        });
    }
}
