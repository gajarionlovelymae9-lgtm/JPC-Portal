<?php

namespace App\Services\AiReports;

use App\Models\AiAnalysis;
use App\Models\AiFinding;
use App\Models\AiSimilarityResult;
use App\Models\AiValidationResult;
use App\Models\Report;
use App\Models\ReportVersion;

/**
 * Generates and updates structured AI Findings (Phase 4).
 *
 * Synthesizes OCR quality, validation results, reference gaps, and
 * similarity flags into actionable finding cards for human review.
 */
class AiFindingsEngine
{
    /**
     * Synthesize findings from validation, references, similarity, and OCR.
     */
    public function generateFindings(Report $report, ReportVersion $version, ?AiAnalysis $analysis = null): void
    {
        // 1. Validation Findings (Missing requirements, weak evidence)
        $validation = $report->aiValidationResults()->where('report_id', $report->id)->latest('id')->first();
        if ($validation instanceof AiValidationResult) {
            foreach ($validation->items as $item) {
                $severity = match (strtolower((string) $item->severity)) {
                    'critical', 'blocker' => 'critical',
                    'high', 'major' => 'high',
                    'low', 'minor' => 'low',
                    default => 'medium',
                };

                $type = match (strtolower((string) $item->category)) {
                    'requirement', 'missing_requirement' => 'missing_requirement',
                    'evidence', 'weak_evidence' => 'weak_evidence',
                    'reference', 'reference_concern' => 'reference_concern',
                    'inconsistency', 'data_inconsistency' => 'data_inconsistency',
                    default => 'incomplete_report',
                };

                AiFinding::updateOrCreate(
                    [
                        'report_id' => $report->id,
                        'report_version_id' => $version->id,
                        'title' => $item->finding,
                    ],
                    [
                        'ai_analysis_id' => $analysis?->id,
                        'finding_type' => $type,
                        'severity' => $severity,
                        'description' => $item->recommendation ?: $item->finding,
                        'evidence' => $item->evidence,
                        'confidence_score' => $validation->confidence_score ?? 0.90,
                        'status' => 'open',
                    ]
                );
            }
        }

        // 2. Similarity Findings (Potential Duplicate / High Similarity)
        $highSimilarities = $report->aiSimilarityResults()
            ->where('similarity_score', '>=', 0.60)
            ->with('comparedReport.employee.user')
            ->get();

        foreach ($highSimilarities as $sim) {
            $scorePct = round($sim->similarity_score * 100, 1);
            $isDuplicate = $sim->similarity_score >= 0.85;

            AiFinding::updateOrCreate(
                [
                    'report_id' => $report->id,
                    'report_version_id' => $version->id,
                    'finding_type' => $isDuplicate ? 'potential_duplicate' : 'weak_evidence',
                    'title' => $isDuplicate
                        ? "Potential Duplicate Submission ({$scorePct}% match)"
                        : "High Content Similarity Detected ({$scorePct}% match)",
                ],
                [
                    'ai_analysis_id' => $analysis?->id,
                    'severity' => $isDuplicate ? 'critical' : 'high',
                    'description' => "Substantial textual overlap with Report #{$sim->compared_report_id}. Similarity is an indicator for review, not proof of copying.",
                    'evidence' => $sim->explanation,
                    'confidence_score' => round($sim->similarity_score, 4),
                    'status' => 'open',
                ]
            );
        }

        // 3. OCR Quality Findings
        $ocr = $report->ocrProcessings()->latest('id')->first();
        if ($ocr && ($ocr->confidence_score !== null && $ocr->confidence_score < 0.70)) {
            AiFinding::updateOrCreate(
                [
                    'report_id' => $report->id,
                    'report_version_id' => $version->id,
                    'finding_type' => 'ocr_concern',
                    'title' => 'Low OCR Confidence Score',
                ],
                [
                    'ai_analysis_id' => $analysis?->id,
                    'severity' => 'medium',
                    'description' => 'Document scan quality is suboptimal (' . round($ocr->confidence_score * 100) . '% confidence). Manual verification of original document is recommended.',
                    'evidence' => 'OCR text extraction confidence below 0.70 threshold.',
                    'confidence_score' => $ocr->confidence_score,
                    'status' => 'open',
                ]
            );
        }
    }
}
