<?php
namespace App\Services\AI;
use App\Models\Employee;
use App\Models\Report;
use App\Models\AiInsight;

class AiInsightService {
    public function generatePerformanceInsight(Employee $employee, string $period): AiInsight {
        return new AiInsight();
    }
    public function generateReportInsight(Report $report): AiInsight {
        return new AiInsight();
    }
    public function generateTrendInsight(Employee $employee): AiInsight {
        return new AiInsight();
    }
    public function generateAnomalyInsight(Employee $employee): AiInsight {
        return new AiInsight();
    }
}