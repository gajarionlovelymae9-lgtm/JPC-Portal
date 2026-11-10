<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AiInsightType;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminInsightService;
use App\Services\AI\AiInsightService;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET  /api/v1/admin/ai-insights
 * GET  /api/v1/admin/ai-insight-options
 * POST /api/v1/admin/ai-insights/generate (Phase B: real-provider, evidence-based, human verification required)
 *
 * IMPORTANT: when no AI pipeline has produced an insight, the payload carries
 * `available: false` and `message: "No AI insight available"`. The frontend
 * must render that empty state rather than a placeholder insight.
 */
class AdminInsightController extends Controller
{
    public function __construct(
        private readonly AdminInsightService $service,
        private readonly AiInsightService $generator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'scope' => ['nullable', 'string', 'in:institution,department,employee'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $data = $this->service->index($filters);
        $data['options'] = $this->service->options();

        return ApiResponse::success('AI insights loaded successfully.', $data);
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success('AI insight options loaded successfully.', $this->service->options());
    }

    public function generate(Request $request): JsonResponse
    {
        $validTypes = implode(',', array_map(fn (AiInsightType $case) => $case->value, AiInsightType::cases()));
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:'.$validTypes],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'period' => ['nullable', 'string', 'max:20'],
        ]);

        if (empty($validated['employee_id']) && empty($validated['report_id'])) {
            return ApiResponse::error('Provide employee_id or report_id to generate an evidence-based insight from authorized records.', [], 422);
        }

        try {
            $insight = $this->generator->generateForAdmin($validated, $request->user());
        } catch (AiProviderConfigurationException $e) {
            return ApiResponse::error($e->getMessage(), [], 503);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('AI insight generation failed. '.$e->getMessage(), [], 422);
        }

        $insight->loadMissing(['employee:id,first_name,last_name,department_id', 'department:id,name']);

        return ApiResponse::success('AI insight generated successfully. Human verification is required before administrative action.', [
            'insight' => [
                'id' => $insight->id,
                'type' => $insight->type?->value,
                'type_label' => $insight->type?->name,
                'content' => $insight->content,
                'metadata' => $insight->metadata,
                'employee_id' => $insight->employee_id,
                'employee' => $insight->employee?->full_name ?? null,
                'department_id' => $insight->department_id,
                'department' => $insight->department?->name,
                'generated_at' => $insight->generated_at?->toIso8601String(),
            ],
            'human_verification_required' => true,
            'advisory_only' => true,
        ], 201);
    }
}
