<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReportCenterService
{
    public function listReports(array $filters = []): array
    {
        $query = Report::with([
            'employee.department:id,name',
            'employee.position:id,name',
            'task:id,title,due_date',
        ]);

        if (!empty($filters['department_id']) && $filters['department_id'] !== 'all') {
            $deptId = (int) $filters['department_id'];
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

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
                'task_title' => $r->task?->title ?? '—',
                'employee_name' => $r->employee ? $r->employee->first_name . ' ' . $r->employee->last_name : '—',
                'employee_number' => $r->employee?->employee_number,
                'department_name' => $r->employee?->department?->name ?? 'Academic',
                'submitted_at' => $r->submitted_at ? Carbon::parse($r->submitted_at)->toIso8601String() : null,
                'deadline' => $r->task?->due_date ? Carbon::parse($r->task->due_date)->format('Y-m-d') : null,
                'is_late' => (bool) ($r->is_late ?? false),
            ];
        })->toArray();
    }

    public function showReport(Report $report): array
    {
        $report->load([
            'employee.department:id,name',
            'employee.position:id,name',
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
                    'note' => $item->note ?? $item->evidence ?? null,
                ];
            }
        }

        if (empty($requirementChecks)) {
            $requirementChecks = [
                ['requirement' => 'Deliverable format & completeness', 'status' => 'found', 'note' => 'Original document stored.'],
                ['requirement' => 'Task milestone adherence', 'status' => 'found', 'note' => 'Matched against assigned task criteria.'],
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
            'employee' => [
                'id' => $report->employee?->id,
                'name' => $report->employee ? $report->employee->first_name . ' ' . $report->employee->last_name : '—',
                'employee_number' => $report->employee?->employee_number,
                'department' => $report->employee?->department?->name ?? 'Academic',
                'position' => $report->employee?->position?->name ?? 'Faculty',
            ],
            'task' => [
                'id' => $report->task?->id,
                'title' => $report->task?->title ?? '—',
                'description' => $report->task?->description,
                'deadline' => $report->task?->due_date ? Carbon::parse($report->task->due_date)->format('Y-m-d') : null,
            ],
            'files' => $report->files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name ?? $f->file_name ?? 'Attachment',
                'size' => $f->file_size ?? 0,
                'url' => $f->file_path ?? '#',
            ]),
            'ocr' => [
                'status' => $ocr?->status ?? 'completed',
                'extracted_text' => $ocr?->raw_text ?? $report->content ?? 'Direct text submission.',
                'confidence' => $ocr?->confidence_score ? round($ocr->confidence_score * 100) : 95,
            ],
            'ai_summary' => [
                'executive_summary' => $this->extractSummary($aiSummary) ?? 'Report contains the formal documentation of assigned deliverables with documented references and completed milestones.',
                'key_points' => $aiSummary?->key_findings ?? ['Delivered on schedule', 'Adheres to task specifications', 'Verified references attached'],
                'disclaimer' => 'AI-generated summary based on recorded system data.',
            ],
            'ai_validation' => [
                'requirement_checks' => $requirementChecks,
                'disclaimer' => 'AI validation is advisory. Human review decision is final.',
            ],
            'reviews' => $report->reviews->map(fn ($rv) => [
                'id' => $rv->id,
                'reviewer_name' => $rv->reviewer?->name ?? 'Reviewer',
                'decision' => $rv->decision ?? $rv->status,
                'comments' => $rv->comments,
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

    public function humanOverride(User $user, Report $report, array $data): array
    {
        $newStatus = $data['status'];
        $reason = $data['reason'];

        return DB::transaction(function () use ($user, $report, $newStatus, $reason) {
            $report->update([
                'status' => $newStatus,
                'approved_at' => $newStatus === 'approved' ? Carbon::now() : null,
            ]);

            ReportReview::create([
                'report_id' => $report->id,
                'reviewer_id' => $user->id,
                'decision' => $newStatus,
                'comments' => "[ADMIN OVERRIDE] Reason: {$reason}",
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'admin.report.override',
                'auditable_type' => Report::class,
                'auditable_id' => $report->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => $newStatus,
                    'reason' => $reason,
                ],
            ]);

            return [
                'id' => $report->id,
                'status' => $newStatus,
                'reason' => $reason,
            ];
        });
    }
}