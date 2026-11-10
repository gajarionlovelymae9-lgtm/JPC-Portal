<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrCalendarService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrCalendarController extends Controller
{
    public function __construct(
        private readonly HrCalendarService $calendar,
    ) {}

    /**
     * Institutional calendar for HR: deadlines, KPI periods, achievements,
     * published announcements (announcements, events and holidays) inside a
     * date range, optionally filtered to one department.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['required_with:from', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return ApiResponse::success(
            'HR calendar loaded successfully.',
            $this->calendar->calendar($filters),
        );
    }
}
