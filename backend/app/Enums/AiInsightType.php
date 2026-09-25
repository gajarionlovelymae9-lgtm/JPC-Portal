<?php

namespace App\Enums;

/**
 * Types of AI-generated insights for performance, reports, and anomaly detection.
 */
enum AiInsightType: string
{
    case PERFORMANCE_INSIGHT = 'PERFORMANCE_INSIGHT';
    case REPORT_INSIGHT      = 'REPORT_INSIGHT';
    case TREND_INSIGHT       = 'TREND_INSIGHT';
    case ANOMALY_INSIGHT     = 'ANOMALY_INSIGHT';
}
