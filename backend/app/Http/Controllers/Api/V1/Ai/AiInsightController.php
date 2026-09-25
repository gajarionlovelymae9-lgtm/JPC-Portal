<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\AiInsightResource;
use App\Models\AiInsight;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    public function index(Request $request)
    {
        $query = AiInsight::query();
        $user = $request->user();

        $role = $user->roleName();

        if (! in_array($role, ['admin', 'hr'], true)) {
            if ($role === 'department_head' && $user->employee) {
                $query->where('department_id', $user->employee->department_id);
            } elseif ($user->employee) {
                $query->where('employee_id', $user->employee->id);
            } else {
                $query->where('id', 0);
            }
        }

        return ApiResponse::success(
            'Insights retrieved successfully',
            AiInsightResource::collection($query->latest()->paginate(15))
        );
    }

    public function performance(Request $request)
    {
        return $this->index($request);
    }

    public function reports(Request $request)
    {
        return $this->index($request);
    }
}