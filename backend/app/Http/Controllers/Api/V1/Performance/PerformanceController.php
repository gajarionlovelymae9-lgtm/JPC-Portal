<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Enums\PerformanceRating;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\PerformancePeriod;
use App\Services\Performance\AchievementEvaluationService;
use App\Services\Performance\PerformanceInsightService;
use App\Services\Performance\PerformanceSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceController extends Controller
{
    public function __construct(
        protected PerformanceSnapshotService $snapshotService,
        protected AchievementEvaluationService $achievementService,
        protected PerformanceInsightService $insightService
    ) {}

    /**
     * Personal performance dashboard for the authenticated employee.
     */
    public function myPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'No associated employee record found.'], 404);
        }

        $year = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);
        $record = EmployeePerformanceRecord::where('employee_id', $employee->id)
            ->where('performance_period_id', $period->id)
            ->with(['breakdowns'])
            ->first();

        if (! $record) {
            $record = $this->snapshotService->calculateEmployeePerformance($employee, $period);
            $record->load('breakdowns');
        }

        // Get personal badges
        $badges = $employee->employeeBadges()
            ->with('badge')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->badge?->code,
                'name' => $b->badge?->name,
                'description' => $b->badge?->description,
                'icon' => $b->badge?->icon,
                'awarded_at' => $b->awarded_at ?? $b->created_at,
            ]);

        // Get recent 6-month historical trend
        $history = $this->getEmployeeTrendSeries($employee, $period);

        $metadata = $record->metadata ?? [];
        $hasActivity = (int) ($metadata['total_tasks'] ?? 0) > 0
            || (int) ($metadata['expected_reports'] ?? 0) > 0
            || (int) ($metadata['submitted_reports'] ?? 0) > 0
            || (float) ($metadata['total_target_val'] ?? 0) > 0
            || (float) ($metadata['achieved_target_val'] ?? 0) > 0;

        $rating = $record->rating
            ?? PerformanceRating::fromScore((float) ($record->productivity_score ?? 0));

        $breakdowns = $record->breakdown_data;
        if (! is_array($breakdowns)) {
            $breakdowns = $record->breakdowns->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'year' => $period->year,
                    'month' => $period->month,
                    'start_date' => $period->start_date,
                    'end_date' => $period->end_date,
                ],
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->title ?? 'Staff',
                ],
                'kpis' => [
                    'task_completion_rate' => (float) $record->completion_rate,
                    'report_completion_rate' => (float) $record->report_completion_rate,
                    'on_time_submission_rate' => (float) $record->on_time_rate,
                    'approval_rate' => (float) $record->approval_rate,
                    'target_achievement_rate' => (float) $record->target_achievement_rate,
                ],
                'productivity_score' => (float) $record->productivity_score,
                'rating' => $rating->value,
                'rating_color' => $rating->color(),
                'calculation_version' => $record->calculation_version,
                'breakdowns' => $breakdowns,
                'metadata' => $metadata,
                'has_activity' => $hasActivity,
                'badges' => $badges,
                'history' => $history,
            ],
        ]);
    }

    /**
     * Historical monthly trend for the authenticated employee.
     */
    public function myHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $period = PerformancePeriod::findOrCreateMonthly((int) now()->year, (int) now()->month);
        $history = $this->getEmployeeTrendSeries($employee, $period, 12);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Paginated list of employee performances for managers / HR / Admin.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeScopeAccess($user);

        $year = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));
        $departmentId = $request->query('department_id');
        $rating = $request->query('rating');
        $search = $request->query('search');

        // Department Head locking
        if ($user->hasRole('department_head') && ! $user->hasRole('admin')) {
            $departmentId = $user->employee?->department_id;
        }

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        $query = EmployeePerformanceRecord::query()
            ->where('performance_period_id', $period->id)
            ->with(['employee.department', 'employee.position']);

        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($rating) {
            $query->where('rating', $rating);
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $records = $query->orderByDesc('productivity_score')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $records,
        ]);
    }

    /**
     * Deep-dive employee performance details.
     */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $user = $request->user();
        $this->authorizeEmployeeAccess($user, $employee);

        $year = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);
        $record = EmployeePerformanceRecord::where('employee_id', $employee->id)
            ->where('performance_period_id', $period->id)
            ->with(['breakdowns', 'employee.department', 'employee.position'])
            ->first();

        if (! $record) {
            $record = $this->snapshotService->calculateEmployeePerformance($employee, $period);
            $record->load(['breakdowns', 'employee.department', 'employee.position']);
        }

        $insights = $this->insightService->generateEmployeeInsights($employee, $period);
        $history = $this->getEmployeeTrendSeries($employee, $period, 6);

        $badges = $employee->employeeBadges()
            ->with('badge')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'record' => $record,
                'insights' => $insights,
                'history' => $history,
                'badges' => $badges,
            ],
        ]);
    }

    /**
     * Leaderboard rankings for a given monthly period.
     */
    public function rankings(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));
        $departmentId = $request->query('department_id');

        if ($user->hasRole('department_head') && ! $user->hasRole('admin')) {
            $departmentId = $user->employee?->department_id;
        }

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        $query = EmployeePerformanceRecord::query()
            ->where('performance_period_id', $period->id)
            ->with(['employee.department'])
            ->orderByDesc('productivity_score');

        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        $records = $query->get();

        // If no records calculated yet, compute them now
        if ($records->isEmpty()) {
            $this->snapshotService->calculateMonthlyPeriod($year, $month);
            $records = $query->get();
        }

        $rankings = $records->map(fn ($r, $idx) => [
            'rank' => $idx + 1,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->full_name,
            'department_name' => $r->employee?->department?->name ?? '—',
            'productivity_score' => (float) $r->productivity_score,
            'rating' => $r->rating?->value ?? (string) $r->rating,
            'completion_rate' => (float) $r->completion_rate,
            'on_time_rate' => (float) $r->on_time_rate,
            'approval_rate' => (float) $r->approval_rate,
            'target_achievement_rate' => (float) $r->target_achievement_rate,
            'is_employee_of_month' => $idx === 0 && (float) $r->productivity_score > 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total' => $rankings->count(),
                'rankings' => $rankings,
            ],
        ]);
    }

    /**
     * Trigger authoritative recalculation for a performance period.
     */
    public function recalculate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['admin', 'hr', 'department_head'])) {
            return response()->json(['message' => 'Unauthorized to trigger recalculation.'], 403);
        }

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $reason = $request->input('reason', 'Manual administrative recalculation');
        $employeeId = $request->input('employee_id');

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $this->authorizeEmployeeAccess($user, $employee);
            $record = $this->snapshotService->calculateEmployeePerformance($employee, $period, null, $user, $reason);

            return response()->json([
                'success' => true,
                'message' => "Performance recalculated for {$employee->full_name}.",
                'data' => $record,
            ]);
        }

        $result = $this->snapshotService->calculateMonthlyPeriod($year, $month, $user, $reason);

        return response()->json([
            'success' => true,
            'message' => "Successfully recalculated performance for {$result['processed_employees']} employees in {$period->name}.",
            'data' => $result,
        ]);
    }

    /**
     * Export performance records to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $this->authorizeScopeAccess($user);

        $year = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));
        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        $records = EmployeePerformanceRecord::where('performance_period_id', $period->id)
            ->with(['employee.department', 'employee.position'])
            ->orderByDesc('productivity_score')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"performance_{$year}_{$month}.csv\"",
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID',
                'Employee Name',
                'Department',
                'Position',
                'Task Completion (%)',
                'Report Completion (%)',
                'On-Time Rate (%)',
                'Approval Rate (%)',
                'Target Achievement (%)',
                'Productivity Score (%)',
                'Rating',
            ]);

            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->employee?->employee_number ?? $r->employee_id,
                    $r->employee?->full_name,
                    $r->employee?->department?->name ?? '—',
                    $r->employee?->position?->title ?? '—',
                    $r->completion_rate,
                    $r->report_completion_rate,
                    $r->on_time_rate,
                    $r->approval_rate,
                    $r->target_achievement_rate,
                    $r->productivity_score,
                    $r->rating?->value ?? (string) $r->rating,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Compute multi-month time series for charts.
     *
     * Only periods with an evaluated record are returned. Months that have
     * never been calculated are omitted instead of being reported as a
     * fabricated zero score.
     */
    private function getEmployeeTrendSeries(Employee $employee, PerformancePeriod $currentPeriod, int $months = 6): array
    {
        $series = [];
        $anchor = Carbon::create($currentPeriod->year, $currentPeriod->month ?? 1, 1);

        for ($i = $months - 1; $i >= 0; $i--) {
            $targetDate = $anchor->copy()->subMonthsNoOverflow($i);
            $mYear = (int) $targetDate->year;
            $mMonth = (int) $targetDate->month;

            $record = EmployeePerformanceRecord::query()
                ->where('employee_id', $employee->id)
                ->where('period_type', 'monthly')
                ->whereDate('period_start', $targetDate->toDateString())
                ->whereNotNull('productivity_score')
                ->orderByDesc('calculated_at')
                ->orderByDesc('id')
                ->first();

            if (! $record) {
                continue;
            }

            $series[] = [
                'period_label' => $targetDate->format('M Y'),
                'year' => $mYear,
                'month' => $mMonth,
                'score' => (float) $record->productivity_score,
                'rating' => $record->rating?->value
                    ?? PerformanceRating::fromScore((float) $record->productivity_score)->value,
            ];
        }

        return $series;
    }

    private function authorizeScopeAccess($user): void
    {
        if (! $user->hasAnyRole(['admin', 'hr', 'department_head'])) {
            abort(403, 'Unauthorized access to performance overview.');
        }
    }

    private function authorizeEmployeeAccess($user, Employee $employee): void
    {
        if ($user->hasRole('admin') || $user->hasRole('hr')) {
            return;
        }

        if ($user->hasRole('department_head')) {
            if ($user->employee?->department_id === $employee->department_id) {
                return;
            }
            abort(403, 'Department Heads can only access employees in their department.');
        }

        if ($user->employee?->id === $employee->id) {
            return;
        }

        abort(403, 'Unauthorized access to another employee performance record.');
    }
}
